<?php

namespace App\Services;

use App\Models\TeacherStudent;
use App\Models\User;

/**
 * Ustoz–o'quvchi bog'lanishlari: taklif havolasi, so'rov, tasdiqlash.
 */
class ConnectionService
{
    public function __construct(
        protected InviteService $inviteService,
        protected AuditService $auditService,
        protected TelegramService $telegram
    ) {}

    public function getInviteLink(User $teacher): string
    {
        $token = $teacher->teacher_approval_token
            ?: $this->inviteService->generateInviteForTeacher($teacher);

        $botUsername = config('telegram.bot_username', 'your_bot');

        return "https://t.me/{$botUsername}?start=join_{$token}";
    }

    /**
     * O'quvchi havola orqali ustozga ulanishni so'raydi.
     *
     * @return array{status: string, teacher: ?User}
     *                                               status: requested | invalid | students_only | already_active | already_pending | rejected
     */
    public function requestJoin(User $student, string $token): array
    {
        $teacher = $this->inviteService->findTeacherByToken($token);

        if (! $teacher || (int) $teacher->id === (int) $student->id) {
            return ['status' => 'invalid', 'teacher' => null];
        }

        if (! $student->isStudent() || ! $student->isActive()) {
            return ['status' => 'students_only', 'teacher' => $teacher];
        }

        $existing = TeacherStudent::where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->first();

        $blocked = match ($existing?->status) {
            'active' => 'already_active',
            'pending' => 'already_pending',
            'rejected' => 'rejected',
            default => null,
        };

        if ($blocked !== null) {
            return ['status' => $blocked, 'teacher' => $teacher];
        }

        $connection = TeacherStudent::updateOrCreate(
            ['teacher_id' => $teacher->id, 'student_id' => $student->id],
            ['status' => 'pending', 'requested_at' => now(), 'approved_at' => null]
        );

        $this->auditService->log(
            'join_requested',
            'connection',
            $connection->id,
            $student,
            ['teacher_id' => $teacher->id]
        );

        $this->telegram->sendMessage(
            $teacher->telegram_user_id,
            __('bot.join_request_notice', ['name' => $student->full_name]),
            [[
                ['text' => __('bot.btn_confirm'), 'callback_data' => 'teacher:approve_student:'.$connection->id],
                ['text' => __('bot.btn_reject'), 'callback_data' => 'teacher:reject_student:'.$connection->id],
            ]]
        );

        return ['status' => 'requested', 'teacher' => $teacher];
    }

    /**
     * requestJoin() natijasiga mos til kaliti.
     */
    public static function joinMessageKey(string $status): string
    {
        return match ($status) {
            'requested' => 'bot.join_requested',
            'students_only' => 'bot.join_students_only',
            'already_active' => 'bot.join_already_active',
            'already_pending' => 'bot.join_already_pending',
            'rejected' => 'bot.join_rejected',
            default => 'bot.join_invalid',
        };
    }

    public function approveStudentRequest(User $teacher, User $student): bool
    {
        if (! $this->inviteService->approveStudent($teacher, $student)) {
            return false;
        }

        $this->auditService->log('student_approved', 'connection', $student->id, $teacher, [
            'student_name' => $student->full_name,
        ]);

        $this->telegram->sendMessage(
            $student->telegram_user_id,
            __('bot.student_connected_notice', ['name' => $teacher->full_name])
        );

        return true;
    }

    public function rejectStudentRequest(User $teacher, User $student): bool
    {
        if (! $this->inviteService->rejectStudent($teacher, $student)) {
            return false;
        }

        $this->auditService->log('student_rejected', 'connection', $student->id, $teacher, [
            'student_name' => $student->full_name,
        ]);

        $this->telegram->sendMessage(
            $student->telegram_user_id,
            __('bot.student_rejected_notice', ['name' => $teacher->full_name])
        );

        return true;
    }

    public function removeStudentAccess(User $teacher, User $student): bool
    {
        if (! $this->inviteService->revokeStudent($teacher, $student)) {
            return false;
        }

        $this->auditService->log('student_removed', 'connection', $student->id, $teacher, [
            'student_name' => $student->full_name,
        ]);

        return true;
    }
}
