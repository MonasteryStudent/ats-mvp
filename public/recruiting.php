<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';

function escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$pageTitle = 'Recruitingübersicht | FiktivFit Karriere';
$headerLinkLabel = 'Stellenangebote';
$headerLinkHref = 'index.php';

require __DIR__ . '/includes/header.php';

?>

<main class="account-page">
    <section
        class="account-intro"
        aria-labelledby="recruiting-heading"
    >
        <p class="eyebrow">Recruitingbereich</p>
        <h1 id="recruiting-heading">Recruitingübersicht</h1>

        <p>
            In diesem Bereich werden Stellen und eingegangene
            Bewerbungen verwaltet.
        </p>
    </section>

    <form action="abmelden.php" method="post">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= escape(csrfToken()) ?>"
        >

        <button class="button button--fit" type="submit">
            Abmelden
        </button>
    </form>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>