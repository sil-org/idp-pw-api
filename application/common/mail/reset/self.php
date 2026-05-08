<?php

use yii\helpers\Html as yHtml;

/**
 * @var string $idpDisplayName
 * @var string $expireTime
 * @var string $resetUrl
 * @var string $helpCenterUrl
 * @var string $displayName
 * @var string $emailSignature
 * @var string $supportName
 * @var string $supportEmail
 * @var string $uiUrl
 */
?>
<p>
    Dear <?= yHtml::encode($displayName) ?>,
</p>
<p>
    Someone recently requested a password change for your <?= yHtml::encode($idpDisplayName) ?> Identity
    account. If this was you, click the link below to set a new password.
    This link is valid until <?= yHtml::encode($expireTime) ?>.
</p>
<p>
    <?= yHtml::a(yHtml::encode($resetUrl), $resetUrl) ?>
</p>
<p>
    If you don't want to change your password or didn't request this, just
    ignore and delete this message.
</p>
<p>
    To keep your account secure, please don't forward this email to anyone.
    <?php if (empty($helpCenterUrl)) { ?>
        If you have any questions, please contact <?= yHtml::encode($supportName) ?> at
        <?= yHtml::encode($supportEmail) ?>.
    <?php } else { ?>
        See our Help Center at <?= yHtml::a(yHtml::encode($helpCenterUrl), $helpCenterUrl) ?> for more security
        tips.
    <?php } ?>
</p>
<p>
    Thanks,
</p>
<p>
    <i><?= nl2br(yHtml::encode($emailSignature), false) ?></i>
</p>
