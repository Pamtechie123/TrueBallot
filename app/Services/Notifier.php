<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\CandidateApplication;
use App\Models\Election;
use App\Models\Position;
use App\Models\User;

/**
 * Every email the site sends lives here, so the wording is easy to find and change.
 * The controllers call one method after something happens, e.g. Notifier::voterStatus($voter, 'approved').
 */
final class Notifier
{
    // ------------------------------------------------------------------ account

    public static function registrationReceived(User $user): void
    {
        self::send($user, 'We received your registration', [
            'Thank you for registering on TrueBallot.',
            'Your account is now waiting for an administrator to review it. We will email you as soon as it is approved.',
        ]);

        foreach (User::admins() as $admin) {
            self::send($admin, 'New voter waiting for approval', [
                '<strong>' . e($user->fullName()) . '</strong> (' . e($user->email()) . ') just registered and is waiting for approval.',
            ], 'Review voters', '/admin/voters?status=pending');
        }
    }

    public static function loginAlert(User $user): void
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ip = trim(explode(',', $ip)[0]);

        self::send($user, 'New login to your TrueBallot account', [
            'Your account was just used to log in.',
            'Time: <strong>' . e(date('j M Y, g:i A')) . '</strong><br>IP address: <strong>' . e($ip) . '</strong>',
            'If this was you, you can ignore this email. If not, reset your password right away.',
        ], 'Reset password', '/forgot-password');
    }

    /** A voter changed their details: tell them it needs approval again, and tell the admins. */
    public static function detailsNeedReview(User $user): void
    {
        self::send($user, 'Your details were updated and need approval', [
            'We saved the changes to your details.',
            'Because your details changed, an administrator must approve your account again. You cannot vote or apply until then. We will email you as soon as it is done.',
        ]);

        foreach (User::admins() as $admin) {
            self::send($admin, 'A voter changed their details', [
                '<strong>' . e($user->fullName()) . '</strong> (' . e($user->email()) . ') changed their details and is waiting for approval again.',
            ], 'Review voters', '/admin/voters?status=pending');
        }
    }

    public static function voterStatus(User $user, string $status): void
    {
        [$subject, $lines, $button] = match ($status) {
            'approved'  => ['Your account has been approved', [
                'Good news! An administrator has approved your account.',
                'You can now log in, see your digital voter card, apply as a candidate and vote when an election opens.',
            ], 'Log in'],
            'rejected'  => ['Your registration was not approved', [
                'An administrator reviewed your registration and did not approve it.',
                'If you think this is a mistake, please contact the administrator.',
            ], null],
            'suspended' => ['Your account has been suspended', [
                'Your TrueBallot account has been suspended and you can no longer log in.',
                'Please contact the administrator if you have questions.',
            ], null],
            default     => [null, [], null],
        };

        if ($subject !== null) {
            self::send($user, $subject, $lines, $button, '/login');
        }
    }

    public static function passwordReset(User $user, string $link, int $minutes): void
    {
        self::send($user, 'Reset your password', [
            'Someone asked to reset the password for your TrueBallot account.',
            'Click the button below to choose a new password. The link works once and expires in ' . $minutes . ' minutes.',
            'If you did not ask for this, you can ignore this email. Your password will not change.',
        ], 'Choose a new password', $link);
    }

    public static function passwordChanged(User $user): void
    {
        self::send($user, 'Your password was changed', [
            'The password for your TrueBallot account was just changed.',
            'If you did not do this, please contact the administrator immediately.',
        ]);
    }

    // ------------------------------------------------------------------ candidates

    /** The administrator added this person as a candidate (only sent when an email was given). */
    public static function candidateAdded(string $email, string $name, string $positionTitle, string $electionTitle): void
    {
        self::sendTo($email, $name, 'You are now a candidate', [
            'You have been added as a candidate for <strong>' . e($positionTitle) . '</strong> in <strong>' . e($electionTitle) . '</strong>.',
            'Your name will appear on the ballot when voting opens.',
        ]);
    }

    public static function applicationReceived(User $user, Position $position, Election $election): void
    {
        self::send($user, 'Candidate application received', [
            'Your application for <strong>' . e($position->title()) . '</strong> in <strong>' . e($election->title()) . '</strong> was received.',
            'An administrator will review it and we will email you the decision.',
        ], 'See my applications', '/candidates/applications');

        foreach (User::admins() as $admin) {
            self::send($admin, 'New candidate application', [
                '<strong>' . e($user->fullName()) . '</strong> applied for <strong>' . e($position->title()) . '</strong> in "' . e($election->title()) . '".',
            ], 'Review applications', '/admin/candidates?status=pending');
        }
    }

    public static function applicationReviewed(CandidateApplication $application, string $decision): void
    {
        $where = '<strong>' . e($application->positionTitle()) . '</strong> in <strong>' . e($application->electionTitle()) . '</strong>';

        $lines = $decision === 'approve'
            ? ['Congratulations! Your application for ' . $where . ' was approved.', 'Your name will appear on the ballot when voting opens.']
            : ['Your application for ' . $where . ' was not approved.', 'Please contact the administrator if you have questions.'];

        self::sendTo(
            $application->applicantEmail(),
            $application->applicantName(),
            $decision === 'approve' ? 'You are now a candidate' : 'Your candidate application was not approved',
            $lines,
            'See my applications',
            '/candidates/applications'
        );
    }

    // ------------------------------------------------------------------ elections and voting

    public static function electionCreated(Election $election): void
    {
        foreach (User::voters('approved') as $voter) {
            self::send($voter, 'New election: ' . $election->title(), [
                'A new election, <strong>' . e($election->title()) . '</strong>, has been created.',
                'Candidate applications: ' . e(format_datetime($election->applicationsOpenAt())) . ' to ' . e(format_datetime($election->applicationsCloseAt())) . '<br>'
                    . 'Voting: ' . e(format_datetime($election->startsAt())) . ' to ' . e(format_datetime($election->endsAt())),
            ], 'Open TrueBallot', '/dashboard');
        }
    }

    public static function voteRecorded(?User $user, Election $election, int $positions): void
    {
        if ($user === null) {
            return;
        }

        self::send($user, 'Your vote has been recorded', [
            'Thank you for voting in <strong>' . e($election->title()) . '</strong>.',
            'We recorded your vote for ' . $positions . ' position' . ($positions === 1 ? '' : 's') . ' on ' . e(date('j M Y, g:i A')) . '.',
            'Your choices are secret and cannot be changed.',
        ]);
    }

    public static function resultsPublished(Election $election): void
    {
        foreach (User::voters('approved') as $voter) {
            self::send($voter, 'Results are out: ' . $election->title(), [
                'The results of <strong>' . e($election->title()) . '</strong> have been published.',
            ], 'See the results', '/results');
        }
    }

    // ------------------------------------------------------------------ helpers

    /** Full web address for a page, for use inside emails. */
    public static function link(string $path): string
    {
        $config = require BASE_PATH . '/config/app.php';
        return $config['url'] . '/' . ltrim($path, '/');
    }

    private static function send(User $user, string $subject, array $lines, ?string $button = null, ?string $link = null): void
    {
        self::sendTo($user->email(), $user->fullName(), $subject, $lines, $button, $link);
    }

    private static function sendTo(string $email, string $name, string $subject, array $lines, ?string $button = null, ?string $link = null): void
    {
        if ($link !== null && !str_starts_with($link, 'http')) {
            $link = self::link($link);
        }

        Mailer::send($email, $name, $subject . ' | TrueBallot', self::template($name, $lines, $button, $link));
    }

    /** One simple layout for every email. The lines may contain HTML, so escape user text before passing it. */
    private static function template(string $name, array $lines, ?string $button, ?string $link): string
    {
        $body = '<p>Hello ' . e($name) . ',</p>';
        foreach ($lines as $line) {
            $body .= '<p>' . $line . '</p>';
        }

        if ($button !== null && $link !== null) {
            $body .= '<p style="margin:24px 0"><a href="' . e($link) . '" style="background:#1d4e89;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;display:inline-block">' . e($button) . '</a></p>';
        }

        return '<!DOCTYPE html><html><body style="margin:0;background:#f3f5f8;font-family:Arial,sans-serif;color:#1f2933">'
            . '<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:8px;overflow:hidden">'
            . '<div style="background:#1d4e89;color:#fff;padding:16px 24px;font-size:20px;font-weight:bold">TrueBallot</div>'
            . '<div style="padding:24px;font-size:15px;line-height:1.6">' . $body . '<p>The TrueBallot team</p></div>'
            . '<div style="padding:12px 24px;font-size:12px;color:#6b7785;background:#f8f9fb">You received this email because you have an account on TrueBallot.</div>'
            . '</div></body></html>';
    }
}