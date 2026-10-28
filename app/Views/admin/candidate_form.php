<?php
/** @var ?\App\Models\Candidate $candidate */
/** @var \App\Models\User[] $voters */
/** @var array $groups  each: ['election' => Election, 'positions' => Position[]] */

$editing = $candidate !== null;
$value = fn (string $field, string $fromCandidate) => old($field, $editing ? $fromCandidate : '');
?>
<h1><?= $editing ? 'Edit candidate' : 'Add a candidate' ?></h1>

<form class="card narrow" method="post" enctype="multipart/form-data"
      action="<?= e(url($editing ? '/admin/candidates/update' : '/admin/candidates/create')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($editing): ?>
        <input type="hidden" name="candidate_id" value="<?= $candidate->id() ?>">
    <?php endif; ?>

    <div class="field">
        <label for="position_id">Position</label>
        <?php if ($editing): ?>
            <input type="text" id="position_id" value="<?= e($candidate->positionTitle() . ' (' . $candidate->electionTitle() . ')') ?>" disabled>
            <p class="hint">The position cannot be changed. Remove the candidate and add them again to move them.</p>
        <?php elseif ($groups === []): ?>
            <p class="notice">There is no election you can add candidates to. Create an election and add a position first, and do it before voting starts.</p>
        <?php else: ?>
            <select id="position_id" name="position_id" required <?= field_attrs('position_id') ?>>
                <option value="">Choose a position</option>
                <?php $chosen = old('position_id'); ?>
                <?php foreach ($groups as $group): ?>
                    <optgroup label="<?= e($group['election']->title()) ?>">
                        <?php foreach ($group['positions'] as $position): ?>
                            <option value="<?= $position->id() ?>" <?= $chosen === (string) $position->id() ? 'selected' : '' ?>>
                                <?= e($position->title()) ?> (minimum age <?= $position->minCandidateAge() ?>)
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?= field_error('position_id') ?>
    </div>

    <div class="field">
        <label for="user_id">Voter</label>
        <?php if ($editing): ?>
            <input type="text" id="user_id" value="<?= e($candidate->displayName() . ', age ' . $candidate->age()) ?>" disabled>
        <?php else: ?>
            <select id="user_id" name="user_id" required <?= field_attrs('user_id') ?>>
                <option value="">Choose an approved voter</option>
                <?php $who = old('user_id'); ?>
                <?php foreach ($voters as $voter): ?>
                    <option value="<?= $voter->id() ?>" <?= $who === (string) $voter->id() ? 'selected' : '' ?>>
                        <?= e($voter->fullName()) ?> (<?= e($voter->voterNumber()) ?>, age <?= $voter->age() ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Only approved voters can be candidates. A voter can stand for one position per election. They get an email when you add them.</p>
        <?php endif; ?>
        <?= field_error('user_id') ?>
    </div>

    <div class="field">
        <label for="party">Party <span class="muted">(optional)</span></label>
        <input type="text" id="party" name="party" maxlength="100"
               value="<?= $value('party', $editing ? $candidate->party() : '') ?>" <?= field_attrs('party') ?>>
        <?= field_error('party') ?>
    </div>

    <div class="field">
        <label for="manifesto">Manifesto</label>
        <textarea id="manifesto" name="manifesto" rows="6" maxlength="2000" required <?= field_attrs('manifesto') ?>><?= $value('manifesto', $editing ? $candidate->manifesto() : '') ?></textarea>
        <p class="hint">What the candidate stands for (20 to 2000 characters). Voters read this on the ballot.</p>
        <?= field_error('manifesto') ?>
    </div>

    <div class="field">
        <label for="photo">Photo <span class="muted">(optional)</span></label>
        <?php if ($editing && $candidate->hasPhoto()): ?>
            <img class="photo-preview" src="<?= e($candidate->photoUrl()) ?>" alt="Current photo" width="80" height="100">
        <?php endif; ?>
        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp"
               data-max-bytes="<?= \App\Services\PhotoStorage::MAX_BYTES ?>" <?= field_attrs('photo') ?>>
        <p class="hint">Optional. Leave empty to use the voter's own registration photo. JPG, PNG or WebP, up to 2 MB.</p>
        <?= field_error('photo') ?>
    </div>

    <div class="field">
        <label for="fee_status">Application fee <span class="muted">(demonstration only, no real payment)</span></label>
        <select id="fee_status" name="fee_status">
            <?php foreach (\App\Models\Candidate::FEE_STATUSES as $fee): ?>
                <option value="<?= e($fee) ?>" <?= $value('fee_status', $editing ? $candidate->feeStatus() : 'unpaid') === $fee ? 'selected' : '' ?>><?= e(label($fee)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($editing || $groups !== []): ?>
        <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Add candidate' ?></button>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(url('/admin/candidates')) ?>">Cancel</a>
</form>