<?php
/**
 * Shared recovery and security questions helpers for SkillConnect.
 */

require_once __DIR__ . '/session.php';

if (!function_exists('getSecurityQuestions')) {
    /**
     * Returns the list of standard security questions.
     */
    function getSecurityQuestions(): array
    {
        return [
            'school' => 'What was the name of your first school?',
            'pet' => 'What was the name of your first pet?',
            'nickname' => 'What was your childhood nickname?',
            'city' => 'In what city were you born?'
        ];
    }
}

if (!function_exists('isRecoveryConfigured')) {
    /**
     * Checks if a user has configured a valid security question and hashed answer.
     */
    function isRecoveryConfigured(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        $question = (string)($user['security_question'] ?? '');
        $answerHash = (string)($user['security_answer_hash'] ?? '');
        if ($question === '' || !array_key_exists($question, getSecurityQuestions()) || $answerHash === '') {
            return false;
        }
        $info = password_get_info($answerHash);
        return !empty($info['algo']);
    }
}

if (!function_exists('isRecoveryFlowActive')) {
    /**
     * Validates that an active, non-expired recovery session is in progress.
     */
    function isRecoveryFlowActive(bool $requireVerified = false): bool
    {
        $flow = $_SESSION['password_recovery'] ?? null;
        if (
            !is_array($flow)
            || empty($flow['user_id'])
            || empty($flow['expires_at'])
            || !is_int($flow['expires_at'])
            || $flow['expires_at'] < time()
        ) {
            unset($_SESSION['password_recovery']);
            return false;
        }

        if ($requireVerified) {
            if (
                empty($flow['verified'])
                || empty($flow['verified_at'])
                || !is_int($flow['verified_at'])
                || ($flow['verified_at'] + 900) < time()
            ) {
                unset($_SESSION['password_recovery']);
                return false;
            }
        }

        return true;
    }
}
