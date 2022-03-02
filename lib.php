<?php

/**
 * @param string $email
 *
 * @return bool
 *
 * @throws Exception
 */
function check_email(string $email): bool
{
    if (\filter_var($email, \FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    // do serious work
    sleep(random_int(1, 60));

    // chaos monkey
    return (bool) \random_int(0, 1);
}

/**
 * Hard check for empty fields. But for general purpose better check only $email and $body,
 * so func signature will be similar to
 * function send_email(string $email, string $body, ?string $from = null, ?string $to = null, ?string $subj = null): void.
 *
 * @param string $email
 * @param string $from
 * @param string $to
 * @param string $subj
 * @param string $body
 *
 * @return void
 *
 * @throws LogicException
 * @throws Exception
 */
function send_email(string $email, string $from, string $to, string $subj, string $body): void
{
    if (
        empty($email) ||
        empty($from) ||
        empty($to) ||
        empty($subj) ||
        empty($body)
    ) {
        throw new LogicException('Empty fields detected', 1);
    }

    // do serious work
    sleep(random_int(1, 10));

    // chaos monkey
    if (\random_int(0, 1) > 0) {
        \printf(
            'Send email to %s <%s>: "%s"',
            $to,
            $email,
            $body
        );

        return;
    }

    throw new LogicException('Chaos monkey say NO', 2);
}

/**
 * @return PDO
 */
function connect(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new \PDO(
            \getenv('PDO_DSN'),
            null,
            null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    return $pdo;
}

/**
 * @return PDOStatement
 *
 * @throws PDOException
 */
function get_users_stm(): PDOStatement
{
    $dbh = \connect();

    $qr = <<<SQL
            SELECT u.*
            FROM users u
            LEFT JOIN emails e ON e.email = u.email
            LEFT JOIN logs l1 ON l1.email = u.email AND u.valid_ts - l1.sent_at_ts < :ts_diff AND l1.is_success = true
            LEFT JOIN logs l2 ON l2.email = u.email AND u.valid_ts - l2.sent_at_ts < :ts_diff AND l2.is_success = false
            WHERE
                u.valid_ts < :expire_ts AND
                (u.is_confirmed = true OR (e.is_checked AND e.is_valid))
            GROUP BY
                u.id,
                u.username,
                u.email,
                u.valid_ts,
                u.is_confirmed
            HAVING
                count(l1.id) = 0 AND
                count(l2.id) < 3
        SQL;

    $ts_diff = 3600 * 24 * 3;
    $expire_ts = \time() + $ts_diff;

    $sth = $dbh->prepare($qr);
    $sth->bindParam('ts_diff', $ts_diff, \PDO::PARAM_INT);
    $sth->bindParam('expire_ts', $expire_ts, \PDO::PARAM_INT);
    $sth->execute();

    return $sth;
}

/**
 * @return PDOStatement
 *
 * @throws PDOException
 */
function get_unchecked_emails_stm(): PDOStatement
{
    $dbh = \connect();

    $qr = <<<SQL
            SELECT u.*
            FROM users u
            LEFT JOIN emails e ON e.email = u.email
            WHERE
                u.is_confirmed = false AND
                (e.email IS NULL OR e.is_checked = false)

        SQL;

    $sth = $dbh->prepare($qr);
    $sth->execute();

    return $sth;
}

/**
 * @param string $email
 * @param bool   $is_valid
 *
 * @return void
 *
 * @throws PDOException
 */
function update_email_checking_state(string $email, bool $is_valid): void
{
    $dbh = \connect();

    $qr = <<<SQL
            UPDATE emails
            SET is_checked = true, is_valid = :is_valid
            WHERE email = :email
        SQL;

    $sth = $dbh->prepare($qr);
    $sth->bindParam('email', $email, \PDO::PARAM_STR);
    $sth->bindParam('is_valid', $is_valid, \PDO::PARAM_BOOL);
    $sth->execute();

    if ($sth->rowCount() === 0) {
        $qr = <<<SQL
                INSERT INTO emails (email, is_checked, is_valid)
                VALUES (:email, true, :is_valid)
            SQL;

        $sth = $dbh->prepare($qr);
        $sth->bindParam('email', $email, \PDO::PARAM_STR);
        $sth->bindParam('is_valid', $is_valid, \PDO::PARAM_BOOL);
        $sth->execute();
    }
}

/**
 * @param string $email
 * @param bool   $is_success
 *
 * @return void
 */
function write_log(string $email, bool $is_success): void
{
    $dbh = \connect();

    $qr = <<<SQL
            INSERT INTO logs (email, sent_at_ts, is_success)
            VALUES (:email, :sent_at_ts, :is_success)
        SQL;

    $now = \time();
    $sth = $dbh->prepare($qr);
    $sth->bindParam('email', $email, \PDO::PARAM_STR);
    $sth->bindParam('sent_at_ts', $now, \PDO::PARAM_INT);
    $sth->bindParam('is_success', $is_success, \PDO::PARAM_BOOL);
    $sth->execute();
}
