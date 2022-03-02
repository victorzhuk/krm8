<?php

\set_time_limit(0);

require_once './lib.php';

const EMAIL_FROM = 'system@system.io';
const EMAIL_SUBJ = 'Your subscription is expiring soon';
const EMAIL_BODY_TPL = '%s, your subscription is expiring soon';

$unchecked_emails = \get_unchecked_emails_stm();
while ($row = $unchecked_emails->fetch()) {
    $email = $row['email'] ?? '';

    $result = \check_email($email);
    \update_email_checking_state($email, $result);
}

$users = \get_users_stm();
while ($user = $users->fetch()) {
    [
        'username' => $username,
        'email' => $email,
    ] = $user;

    try {
        \send_email(
            $email,
            EMAIL_FROM,
            $username,
            EMAIL_SUBJ,
            \sprintf(EMAIL_BODY_TPL, $username)
        );
        \write_log($email, true);
    } catch (LogicException $e) {
        \write_log($email, false);
    }
}
