<?php
/** @var \App\Models\Position[] $positions */
/** @var \App\Models\User $user */
$selected = old('position_id');
?>
<h1>Apply as a candidate</h1>
<p class="lead">You can apply for a position while its application period is running. An administrator reviews every application.</p>

<?php if ($positions === []): ?>
    <div class="notice">
        <strong>No positions are accepting applications right now.</strong>
        You can apply while an election's application period is open and the administrator has added at least one position to it.
        <a href="<?= e(url('/dashboard')) ?>">See all elections</a>
    </div>
<?php else: ?>
    <form class="card narrow" method="post" action="<?= e(url('/candidates/apply')) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <div class="field">
            <label for="position_id">Position</label>
            <select id="position_id" name="position_id" <?= field_attrs('position_id') ?>>
                <option value="">Choose a position</option>
                <?php foreach ($positions as $position): ?>
                    <option value="<?= $position->id() ?>" <?= $selected === (string) $position->id() ? 'selected' : '' ?>>
                        <?= e($position->title() . ' (' . $position->electionTitle() . ', minimum age ' . $position->minCandidateAge() . ', fee ' . ($position->feeAmount() > 0 ? naira($position->feeAmount()) : 'free') . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= field_error('position_id') ?>
        </div>

        <div class="field">
            <label for="display_name">Name to show on the ballot</label>
            <input id="display_name" name="display_name" type="text" maxlength="150" value="<?= old('display_name', $user->fullName()) ?>" <?= field_attrs('display_name') ?>>
            <?= field_error('display_name') ?>
        </div>

        <div class="field">
            <label for="manifesto">Your manifesto</label>
            <textarea id="manifesto" name="manifesto" rows="6" maxlength="2000" <?= field_attrs('manifesto') ?>><?= old('manifesto') ?></textarea>
            <p class="hint">Tell voters what you stand for (20 to 2000 characters).</p>
            <?= field_error('manifesto') ?>
        </div>

        <?php $hasPhoto = \App\Services\PhotoStorage::exists($user->id()); ?>
        <div class="field">
            <label for="photo">Your photo <?= $hasPhoto ? '<span class="muted">(optional, replaces your current photo)</span>' : '' ?></label>
            <?php if ($hasPhoto): ?>
                <img class="photo-preview" src="<?= e(url('/photo?v=' . \App\Services\PhotoStorage::version($user->id()))) ?>" alt="Your current photo" width="80" height="100">
            <?php endif; ?>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-max-bytes="<?= \App\Services\PhotoStorage::MAX_BYTES ?>" <?= field_attrs('photo') ?>>
            <p class="hint">Shown next to your name on the ballot. JPG, PNG or WebP, up to 2 MB.</p>
            <?= field_error('photo') ?>
        </div>

        <p class="notice">The application fee for each position is shown in the list above. This is a demonstration:
            no payment is taken here, an administrator records the fee.</p>

        <button type="submit" class="btn">Submit application</button>
    </form>
<?php endif; ?>