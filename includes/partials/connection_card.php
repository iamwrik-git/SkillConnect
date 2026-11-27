<?php
/**
 * includes/partials/connection_card.php - Modular Trainer/Trainee card matching reference style (Image 4)
 */
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../session.php';

$photoUrl = getProfilePhotoUrl($card_photo ?? null, $card_name ?? 'User');
$primarySkill = !empty($card_primary_skill) ? $card_primary_skill : (!empty($card_skills[0]) ? $card_skills[0] : '');
$expLabel = !empty($card_exp_level) ? ucfirst($card_exp_level) : '';
$roleBadge = !empty($card_role_label) ? $card_role_label : 'Mentee';
$roleSlug = strtolower($roleBadge);
$status = $card_status ?? 'accepted';
$showUnlink = !empty($card_show_unlink) && !empty($card_request_id);
$isIncoming = !empty($card_is_incoming_request);
$formattedDate = !empty($card_date) ? $card_date : '';
$returnTo = !empty($card_return_to) ? $card_return_to : 'mentorships.php';
$isCompact = !empty($card_compact);
$viewLabel = !empty($card_view_label) ? $card_view_label : 'View Profile';
?>
<div class="connection-card connection-card-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?><?= $isCompact ? ' connection-card-compact' : '' ?>" data-user-id="<?= (int)$card_user_id ?>">
    <div class="connection-left">
        <img src="<?= $photoUrl ?>" alt="<?= htmlspecialchars($card_name ?? 'User', ENT_QUOTES, 'UTF-8') ?>" class="connection-avatar">
        <div class="connection-info">
            <h4 class="connection-name">
                <a href="profile.php?user_id=<?= (int)$card_user_id ?>"><?= htmlspecialchars($card_name ?? 'User', ENT_QUOTES, 'UTF-8') ?></a>
            </h4>
            <div class="connection-subtitle">
                <?php if (!empty($expLabel) && $roleSlug !== 'trainee' && $roleSlug !== 'mentee'): ?>
                    <span class="connection-exp"><?= htmlspecialchars($expLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="connection-sep" aria-hidden="true">•</span>
                <?php endif; ?>
                <span class="badge-pill badge-pill-role badge-pill-role-<?= $roleSlug ?>"><?= htmlspecialchars($roleBadge, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($card_skills)): ?>
        <div class="connection-middle">
            <div class="connection-badges">
                <?php foreach (array_slice($card_skills, 0, 3) as $sk): ?>
                    <span class="badge-pill badge-pill-skill"><?= htmlspecialchars(trim($sk), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="connection-actions">
        <?php if ($status === 'accepted'): ?>
            <a href="profile.php?user_id=<?= (int)$card_user_id ?>" class="btn-card-outline" title="<?= htmlspecialchars($viewLabel, ENT_QUOTES, 'UTF-8') ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <span><?= htmlspecialchars($viewLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <a href="messages.php?user_id=<?= (int)$card_user_id ?>" 
               class="btn-card-primary" 
               title="Message <?= htmlspecialchars($card_name ?? 'User', ENT_QUOTES, 'UTF-8') ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <span>Message</span>
            </a>
            <?php if ($showUnlink): ?>
                <div class="mentorship-more" data-mentorship-menu>
                    <button type="button" class="mentorship-more-toggle" aria-label="More mentorship options" aria-expanded="false" aria-haspopup="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                            <circle cx="12" cy="5" r="1.5"></circle>
                            <circle cx="12" cy="12" r="1.5"></circle>
                            <circle cx="12" cy="19" r="1.5"></circle>
                        </svg>
                    </button>
                    <div class="mentorship-more-menu" hidden>
                        <form action="actions/mentorship_process.php" method="POST" class="js-confirm-unlink-mentorship" data-connection-name="<?= htmlspecialchars($card_name ?? 'User', ENT_QUOTES, 'UTF-8') ?>" data-connection-role="<?= strtolower($roleBadge) ?>">
                            <input type="hidden" name="action" value="unlink_mentorship">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="request_id" value="<?= (int)$card_request_id ?>">
                            <button type="submit" class="mentorship-unlink-action">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 6h18"></path>
                                    <path d="M8 6V4h8v2"></path>
                                    <path d="M19 6l-1 14H6L5 6"></path>
                                    <path d="M10 11v5M14 11v5"></path>
                                </svg>
                                <span><strong>Unlink <?= htmlspecialchars($roleBadge, ENT_QUOTES, 'UTF-8') ?></strong><small>Remove connection</small></span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($status === 'pending'): ?>
            <?php if ($isIncoming): ?>
                <form action="actions/mentorship_process.php" method="POST" class="inline-form" style="margin:0;">
                    <input type="hidden" name="action" value="accept_request">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="request_id" value="<?= (int)$card_request_id ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-accept btn-sm" data-loading-text="Accepting...">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6L9 17l-5-5"></path>
                        </svg>
                        <span>Accept</span>
                    </button>
                </form>
                <form action="actions/mentorship_process.php" method="POST" class="inline-form js-confirm-reject-request" style="margin:0;">
                    <input type="hidden" name="action" value="reject_request">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="request_id" value="<?= (int)$card_request_id ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-reject btn-sm" data-loading-text="Rejecting...">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        <span>Reject</span>
                    </button>
                </form>
            <?php else: ?>
                <span class="badge-pill badge-pill-pending">Pending</span>
            <?php endif; ?>
            <a href="profile.php?user_id=<?= (int)$card_user_id ?>" class="btn-card-outline" title="<?= htmlspecialchars($viewLabel, ENT_QUOTES, 'UTF-8') ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <span><?= htmlspecialchars($viewLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php elseif ($status === 'rejected'): ?>
            <span class="badge-pill badge-pill-rejected">Rejected</span>
            <?php if (!empty($formattedDate)): ?>
                <span class="connection-date"><?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <a href="profile.php?user_id=<?= (int)$card_user_id ?>" class="btn-card-outline" title="<?= htmlspecialchars($viewLabel, ENT_QUOTES, 'UTF-8') ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <span><?= htmlspecialchars($viewLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endif; ?>
    </div>
</div>
