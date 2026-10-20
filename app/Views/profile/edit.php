<?php
/** @var \App\Models\User $user */
use App\Interfaces\RoleInterface;

$isAdmin = $user->canAccess(RoleInterface::AREA_ADMIN);
?>
<h1>My profile</h1>
<p>Account status: <?= status_badge($user->status()) ?> &nbsp; Email: <strong><?= e($user->email()) ?></strong></p>

<section class="card narrow" aria-labelledby="details-heading">
    <h2 id="details-heading">My details</h2>

    <?php if (!$isAdmin): ?>
        <div class="notice">
            If you change any of these details, an administrator must approve your account again.
            You will not be able to vote or apply until then.
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/profile')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="field">
            <label for="full_name">Full name</label>
            <input type="text" id="full_name" name="full_name" value="<?= old('full_name', $user->fullName()) ?>" maxlength="150" autocomplete="name" required <?= field_attrs('full_name') ?>>
            <?= field_error('full_name') ?>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="age">Age</label>
                <input type="number" id="age" name="age" value="<?= old('age', (string) $user->age()) ?>" min="18" max="120" inputmode="numeric" required <?= field_attrs('age') ?>>
                <?= field_error('age') ?>
            </div>
            <div class="field">
                <label for="state">State</label>
                <input type="text" id="state" name="state" value="<?= old('state', $user->state()) ?>" maxlength="100" required <?= field_attrs('state') ?>>
                <?= field_error('state') ?>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="sex">Sex</label>
                <select id="sex" name="sex" required <?= field_attrs('sex') ?>>
                    <?php if (!in_array(old('sex', $user->sex()), ['male', 'female'], true)): ?><option value="" selected>Choose...</option><?php endif; ?>
                    <?php foreach (['male', 'female'] as $option): ?>                        <option value="<?= e($option) ?>" <?= old('sex', $user->sex()) === $option ? 'selected' : '' ?>><?= e(label($option)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error('sex') ?>
            </div>
            <div class="field">
                <label for="disability_status">Do you have a disability?</label>
                <select id="disability_status" name="disability_status" required <?= field_attrs('disability_status') ?>>
                    <?php if (!in_array(old('disability_status', $user->disabilityStatus()), ['yes', 'no'], true)): ?><option value="" selected>Choose...</option><?php endif; ?>
                    <?php foreach (['yes', 'no'] as $option): ?>                        <option value="<?= e($option) ?>" <?= old('disability_status', $user->disabilityStatus()) === $option ? 'selected' : '' ?>><?= e(label($option)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error('disability_status') ?>
            </div>
        </div>

        <button type="submit" class="btn">Save my details</button>
    </form>
</section>

<section class="card narrow" aria-labelledby="password-heading">
    <h2 id="password-heading">Change my password</h2>
    <p class="hint">Changing your password does not need approval.</p>

    <form method="post" action="<?= e(url('/profile/password')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="field">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password" required <?= field_attrs('current_password') ?>>
            <?= field_error('current_password') ?>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required <?= field_attrs('new_password') ?>>
                <p class="hint">At least 8 characters.</p>
                <?= field_error('new_password') ?>
            </div>
            <div class="field">
                <label for="new_password_confirm">Confirm new password</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" required <?= field_attrs('new_password_confirm') ?>>
                <?= field_error('new_password_confirm') ?>
            </div>
        </div>

        <button type="submit" class="btn">Change password</button>
    </form>
</section>