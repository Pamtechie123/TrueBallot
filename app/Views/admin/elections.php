<?php
/** @var array $items  each: ['election' => Election, 'positions' => [['position' => Position, 'candidates' => int]]] */
?>
<h1>Elections</h1>

<section aria-labelledby="new-heading">
    <h2 id="new-heading">Create an election</h2>
    <p class="muted">Set four dates. Everything else happens by itself:
        applications open, then close, then voting starts, then voting ends.</p>

    <form class="card" method="post" action="<?= e(url('/admin/elections/create')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="field">
            <label for="title">Title</label>
            <input id="title" name="title" type="text" maxlength="200" value="<?= old('title') ?>" <?= field_attrs('title') ?>>
            <?= field_error('title') ?>
        </div>

        <div class="field">
            <label for="description">Description (optional)</label>
            <textarea id="description" name="description" rows="2" maxlength="1000" <?= field_attrs('description') ?>><?= old('description') ?></textarea>
            <?= field_error('description') ?>
        </div>

        <h3>1. When can voters apply as candidates?</h3>
        <div class="field-row">
            <div class="field">
                <label for="applications_open_at">Applications open</label>
                <input id="applications_open_at" name="applications_open_at" type="datetime-local" value="<?= old('applications_open_at', datetime_input(0)) ?>" <?= field_attrs('applications_open_at') ?>>
                <?= field_error('applications_open_at') ?>
            </div>
            <div class="field">
                <label for="applications_close_at">Applications close</label>
                <input id="applications_close_at" name="applications_close_at" type="datetime-local" value="<?= old('applications_close_at', datetime_input(2)) ?>" <?= field_attrs('applications_close_at') ?>>
                <?= field_error('applications_close_at') ?>
            </div>
        </div>

        <h3>2. When can voters vote?</h3>
        <div class="field-row">
            <div class="field">
                <label for="starts_at">Voting starts</label>
                <input id="starts_at" name="starts_at" type="datetime-local" value="<?= old('starts_at', datetime_input(3)) ?>" <?= field_attrs('starts_at') ?>>
                <?= field_error('starts_at') ?>
            </div>
            <div class="field">
                <label for="ends_at">Voting ends</label>
                <input id="ends_at" name="ends_at" type="datetime-local" value="<?= old('ends_at', datetime_input(4)) ?>" <?= field_attrs('ends_at') ?>>
                <?= field_error('ends_at') ?>
            </div>
        </div>
        <p class="hint">Times are Nigerian time (Africa/Lagos). Voting must start after applications close.</p>

        <button type="submit" class="btn">Create election</button>
    </form>
</section>

<section aria-labelledby="list-heading">
    <h2 id="list-heading">All elections</h2>

    <?php if ($items === []): ?>
        <p>No elections yet. Create the first one above.</p>
    <?php endif; ?>

    <?php foreach ($items as $item):
        $election = $item['election'];
        $phase    = $election->phase();
        ?>
        <article class="card election">
            <header class="election-head">
                <h3><?= e($election->title()) ?></h3>
                <?= phase_badge($phase) ?>
            </header>

            <?php if ($election->description() !== ''): ?>
                <p><?= e($election->description()) ?></p>
            <?php endif; ?>

            <ul class="timeline">
                <li><strong>Applications:</strong> <?= e(format_datetime($election->applicationsOpenAt())) ?> to <?= e(format_datetime($election->applicationsCloseAt())) ?></li>
                <li><strong>Voting:</strong> <?= e(format_datetime($election->startsAt())) ?> to <?= e(format_datetime($election->endsAt())) ?></li>
            </ul>

            <h4>Positions</h4>
            <?php if ($item['positions'] === []): ?>
                <p class="muted">No positions yet. Add at least one so voters can apply.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th scope="col">Position</th><th scope="col" class="num">Minimum age</th><th scope="col" class="num">Application fee</th><th scope="col" class="num">Candidates on ballot</th></tr></thead>
                        <tbody>
                        <?php foreach ($item['positions'] as $row): ?>
                            <tr>
                                <td><?= e($row['position']->title()) ?></td>
                                <td class="num"><?= $row['position']->minCandidateAge() ?></td>
                                <td class="num"><?= $row['position']->feeAmount() > 0 ? e(naira($row['position']->feeAmount())) : 'Free' ?></td>
                                <td class="num"><?= (int) $row['candidates'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($election->canEditSetup()): ?>
                <form class="inline-fields" method="post" action="<?= e(url('/admin/positions/create')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="election_id" value="<?= $election->id() ?>">
                    <div class="field">
                        <label for="pos-title-<?= $election->id() ?>">New position</label>
                        <input id="pos-title-<?= $election->id() ?>" name="title" type="text" maxlength="150" required>
                    </div>
                    <div class="field">
                        <label for="pos-age-<?= $election->id() ?>">Minimum age</label>
                        <input id="pos-age-<?= $election->id() ?>" name="min_age" type="number" min="18" max="100" value="18" required>
                    </div>
                    <div class="field">
                        <label for="pos-fee-<?= $election->id() ?>">Application fee (₦)</label>
                        <input id="pos-fee-<?= $election->id() ?>" name="fee" type="number" min="0" step="0.01" value="0" required>
                    </div>
                    <button type="submit" class="btn btn-secondary">Add position</button>
                </form>
            <?php endif; ?>

            <?php
            // Buttons to move the election on now, instead of waiting for the dates.
            $shortcut = match ($phase) {
                'applications_open' => ['/admin/elections/close-applications', 'Close applications now', 'Stop accepting candidate applications for this election now?', 'Candidates can apply until you close applications or the closing date passes.'],
                'waiting'           => ['/admin/elections/start-voting', 'Start voting now', 'Open voting for this election now?', 'Applications are closed. Voting starts on the date above, or now if you click the button.'],
                'voting_open'       => ['/admin/elections/end-voting', 'End voting now', 'End voting now? Voters will no longer be able to vote.', 'End voting to be able to publish the results.'],
                default             => null,
            };
            ?>
            <?php if ($shortcut !== null): [$action, $text, $question, $help] = $shortcut; ?>
                <form class="status-form" method="post" action="<?= e(url($action)) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="election_id" value="<?= $election->id() ?>">
                    <button type="submit" class="btn" data-confirm="<?= e($question) ?>"><?= e($text) ?></button>
                    <span class="hint"><?= e($help) ?></span>
                </form>
            <?php endif; ?>

            <?php if ($election->canPublishResults()): ?>
                <form class="status-form" method="post" action="<?= e(url('/admin/elections/publish')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="election_id" value="<?= $election->id() ?>">
                    <button type="submit" class="btn"
                            data-confirm="<?= e('Publish the results of "' . $election->title() . '"? Every voter will be able to see them.') ?>">Publish results</button>
                    <span class="hint">Voting has ended. Publishing lets every voter see the results.</span>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>