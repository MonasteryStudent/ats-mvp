<?php

$pageTitle = $pageTitle ?? 'FiktivFit Karriere';
$headerLinkLabel = $headerLinkLabel ?? 'Mein Konto';
$headerLinkHref = $headerLinkHref ?? 'anmelden.php?from=overview';

?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(
        $pageTitle,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css?v=5">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="index.php">FiktivFit Karriere</a>

        <nav aria-label="Hauptnavigation">
            <a href="<?= htmlspecialchars(
                $headerLinkHref,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>">
                <?= htmlspecialchars(
                    $headerLinkLabel,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </a>
        </nav>
    </header>