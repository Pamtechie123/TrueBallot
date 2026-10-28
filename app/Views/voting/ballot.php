<?php
/** @var array $ballots  each: ['election' => Election, 'positions' => [['position','candidates','votedFor']], 'canStillVote' => bool] */
?>
<h1>Vote</h1>

<?php if ($ballots === []): ?>
    <div class="notice">There is no election open for voting right now.</div>
<?php endif; ?>

<?php foreach ($ballots as $ballot):
    $election = $ballot['election'];
    ?>
    <section class="card election" aria-labelledby="election-<?= $election->id() ?>">
        <h2 id="election-<?= $election->id() ?>"><?= e($election->title()) ?></h2>
        <p class="muted">Voting closes <?= e(format_datetime($election->endsAt())) ?>. You vote once per position and cannot change your vote.</p>

        <form method="post" action="<?= e(url('/vote/cast')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="election_id" value="<?= $election->id() ?>">

            <?php foreach ($ballot['positions'] as $row):
                $position = $row['position'];
                ?>
                <fieldset class="ballot-position">
                    <legend><?= e($position->title()) ?></legend>

                    <?php if ($row['votedFor'] !== null): ?>
                        <p class="voted">You voted for <strong><?= e($row['votedFor']) ?></strong>. Thank you.</p>
                    <?php elseif ($row['candidates'] === []): ?>
                        <p class="muted">No approved candidates for this position.</p>
                    <?php else: ?>
                        <?php foreach ($row['candidates'] as $candidate):
                            $inputId = 'c-' . $position->id() . '-' . $candidate->id();
                            ?>
                            <div class="option">
                                <input type="radio" id="<?= $inputId ?>" name="choice[<?= $position->id() ?>]" value="<?= $candidate->id() ?>">
                                <?php if ($candidate->hasPhoto()): ?>
                                    <img class="candidate-photo" src="<?= e($candidate->photoUrl()) ?>" alt="" width="64" height="80">
                                <?php endif; ?>
                                <label for="<?= $inputId ?>">
                                    <?= e($candidate->displayName()) ?><?= $candidate->party() !== '' ? ' <span class="muted">(' . e($candidate->party()) . ')</span>' : '' ?>
                                    <span class="option-manifesto"><?= e($candidate->manifesto()) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </fieldset>
            <?php endforeach; ?>

            <?php if ($ballot['canStillVote']): ?>
                <button type="submit" class="btn" data-confirm="Submit your vote? You cannot change it afterwards.">Submit my vote</button>
                <p class="hint">Positions you leave unselected stay open for you to vote on later.</p>
            <?php else: ?>
                <p class="voted">You have finished voting in this election.</p>
            <?php endif; ?>
        </form>
    </section>
<?php endforeach; ?>