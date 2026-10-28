<section class="hero-band">
    <div class="hero-inner">
        <div>
            <span class="hero-kicker">SECURE · TRANSPARENT · SIMPLE</span>
            <h1>Every vote <em>counts</em>. Every election, in one place.</h1>
            <p class="lead">TrueBallot takes an election from registration to published results. Approved voters vote once per position, and candidates are registered by the election administrator.</p>

            <?php if ($currentUser === null): ?>
                <p class="actions">
                    <a class="btn btn-gold" href="<?= e(url('/register')) ?>">Register to vote</a>
                    <a class="btn btn-light" href="<?= e(url('/login')) ?>">Log in</a>
                </p>
            <?php else: ?>
                <p class="actions">
                    <a class="btn btn-gold" href="<?= e(url($currentUser->homePath())) ?>">Go to my dashboard</a>
                </p>
            <?php endif; ?>
        </div>
        <div class="hero-art" aria-hidden="true">
            <svg viewBox="0 0 320 300">
                <ellipse cx="160" cy="280" rx="120" ry="14" fill="#000" opacity=".18"/>
                <rect x="40" y="130" width="240" height="140" rx="18" fill="#f5b301"/>
                <rect x="40" y="130" width="240" height="34" rx="14" fill="#ffd05a"/>
                <rect x="105" y="118" width="110" height="20" rx="10" fill="#0b1f4b"/>
                <g transform="rotate(-6 160 80)">
                    <rect x="95" y="22" width="130" height="110" rx="10" fill="#fff"/>
                    <rect x="112" y="40" width="60" height="9" rx="4.5" fill="#c9d6ee"/>
                    <rect x="112" y="58" width="96" height="9" rx="4.5" fill="#c9d6ee"/>
                    <circle cx="124" cy="96" r="14" fill="#0f8a4f"/>
                    <path d="m117 96 5 5 9-11" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    <rect x="148" y="90" width="58" height="9" rx="4.5" fill="#c9d6ee"/>
                </g>
                <text x="160" y="220" text-anchor="middle" font-family="Plus Jakarta Sans, Arial" font-weight="800" font-size="30" fill="#0b1f4b">VOTE</text>
            </svg>
        </div>
    </div>
</section>

<section aria-labelledby="how-heading">
    <h2 id="how-heading">How it works</h2>
    <div class="feature-grid">
        <div class="feature"><div class="num-badge">1</div><h3>Register</h3><p>Create your account with your details and a clear photo.</p></div>
        <div class="feature"><div class="num-badge">2</div><h3>Get approved</h3><p>The administrator reviews your registration and issues your digital voter card.</p></div>
        <div class="feature"><div class="num-badge">3</div><h3>Vote</h3><p>When voting opens, choose your candidate for each position. You vote once per position.</p></div>
        <div class="feature"><div class="num-badge">4</div><h3>See results</h3><p>Results appear once the administrator publishes them.</p></div>
    </div>
</section>

<div class="notice">
    <strong>Demonstration prototype.</strong> Votes are linked to voter accounts to prevent double voting, so this version is not a secret ballot and is not for real public elections.
</div>