<section class="hero">
    <h1>Online elections, from registration to results</h1>
    <p class="lead">TrueBallot lets approved voters take part in elections, apply to contest a position, and see published results.</p>

    <?php if ($currentUser === null): ?>
        <p class="actions">
            <a class="btn" href="<?= e(url('/register')) ?>">Register to vote</a>
            <a class="btn btn-secondary" href="<?= e(url('/login')) ?>">Log in</a>
        </p>
    <?php else: ?>
        <p class="actions">
            <a class="btn" href="<?= e(url($currentUser->homePath())) ?>">Go to my dashboard</a>
        </p>
    <?php endif; ?>
</section>

<section aria-labelledby="how-heading">
    <h2 id="how-heading">How it works</h2>
    <ol class="steps">
        <li><strong>Register.</strong> Create an account with your details.</li>
        <li><strong>Get approved.</strong> An administrator reviews your registration.</li>
        <li><strong>Receive your voter card.</strong> Once approved, your digital voter card appears on your dashboard.</li>
        <li><strong>Vote or contest.</strong> Vote once for each position in an open election, or apply to be a candidate.</li>
        <li><strong>See the results.</strong> Results appear after the administrator publishes them.</li>
    </ol>
</section>

<div class="notice">
    <strong>Demonstration prototype.</strong> Votes are linked to voter accounts to prevent double voting, so this version is not a secret ballot and is not for real public elections.
</div>
