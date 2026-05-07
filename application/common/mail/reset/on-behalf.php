<?php

use yii\helpers\Html as yHtml;

/**
 * @var string $name
 * @var string $idpDisplayName
 * @var string $expireTime
 * @var string $resetUrl
 * @var string $helpCenterUrl
 * @var string $emailSignature
 * @var string $displayName
 * @var string $supportName
 * @var string $supportEmail
 * @var string $uiUrl
 */
?>
<p>
    Hi there,
</p>
<p>
    <?= yHtml::encode($displayName) ?> recently requested a password change for their
    <?= yHtml::encode($idpDisplayName) ?> Identity account.
</p>
<p>
    If this was you, please use the link below to reset your password.
</p>
<p>
    If it's not you but you do know them, you may have been sent this link because they requested
    it sent to you - their recovery contact. You may provide the link for them to use, but <i>please
    contact them directly</i> to ensure that you are only providing the link to them and not to
    someone else.
</p>
<p>
    If you are this person's supervisor or sponsor, please have them update their password recovery
    address in the <?= yHtml::a('profile manager', \Yii::$app->params['uiUrl']) ?>.
</p>
<p>
    <?= yHtml::a(yHtml::encode($resetUrl), $resetUrl) ?>
</p>
<p>This link is valid
    until <?= yHtml::encode($expireTime) ?>.
</p>
<p>
    To maintain security, please don't forward this email to anyone.
</p>
<p>
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
