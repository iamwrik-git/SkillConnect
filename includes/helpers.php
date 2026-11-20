<?php
/**
 * Shared utility functions for SkillConnect.
 */

if (!function_exists('getProfilePhotoUrl')) {
    /**
     * Resolves the profile photo URL or generates a fallback avatar.
     * Automatically adjusts relative path if called from an admin or action script.
     */
    function getProfilePhotoUrl(?string $photo, ?string $name, int $size = 120): string
    {
        $nameStr = is_string($name) && trim($name) !== '' ? trim($name) : 'User';
        if (empty($photo) || $photo === 'default.jpg') {
            return "https://ui-avatars.com/api/?name=" . urlencode($nameStr) . "&background=eff6ff&color=2563eb&size=" . (int)$size . "&bold=true";
        }

        $safePhoto = basename((string)$photo);
        $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
        $prefix = in_array(basename($scriptDir), ['admin', 'actions'], true) ? '../' : '';

        return $prefix . 'assets/images/profile/' . rawurlencode($safePhoto);
    }
}
