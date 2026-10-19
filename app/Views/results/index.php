<?php
/** @var array $results  each: ['election' => Election, 'positions' => tally, 'voters' => int] */
/** @var int $approvedVoters */
/** @var bool $isAdmin */
?>
<h1>Results</h1>

<?php if ($isAdmin): ?>
    <p class="notice">As an administrator you can follow results while voting is open. Voters see them only after you publish.</p>
<?php endif; ?>

<?php if ($results === []): ?>
    <div class="notice">No results have been published yet.</div>
<?php endif; ?>

<?php foreach ($results as $result):
    $election = $result['election'];
    $turnout  = $approvedVoters > 0 ? round($result['voters'] / $approvedVoters * 100) : 0;
    ?>
    <section class="card election" aria-labelledby="res-<?= $election->id() ?>">
        <header class="election-head">
            <h2 id="res-<?= $election->id() ?>"><?= e($election->title()) ?></h2>
            <?= status_badge($election->status()) ?>
        </header>
        <p class="muted"><?= (int) $result['voters'] ?> of <?= (int) $approvedVoters ?> approved voters voted (<?= (int) $turnout ?>%).</p>

        <?php foreach ($result['positions'] as $position):
            $max = 0;
            foreach ($position['candidates'] as $c) {
                $max = max($max, (int) $c['votes']);
            }
            ?>
            <h3><?= e($position['title']) ?> <span class="muted">(<?= (int) $position['total'] ?> votes)</span></h3>

            <?php if ($position['candidates'] === []): ?>
                <p class="muted">No candidates.</p>
            <?php else: ?>
                <ul class="bars">
                    <?php foreach ($position['candidates'] as $c):
                        $votes   = (int) $c['votes'];
                        $percent = $position['total'] > 0 ? round($votes / $position['total'] * 100) : 0;
                        $leading = $votes > 0 && $votes === $max;
                        ?>
                        <li>
                            <span class="bar-name"><?= e($c['name']) ?><?= $leading ? ' (leading)' : '' ?></span>
                            <span class="bar-track"><span class="bar-fill" style="width: <?= (int) $percent ?>%"></span></span>
                            <span class="bar-value"><?= $votes ?> (<?= (int) $percent ?>%)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>
