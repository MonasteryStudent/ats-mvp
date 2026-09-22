<?php

declare(strict_types=1);

$pageTitle = 'Impressum | FiktivFit Karriere';
$headerLinkLabel = 'Zur Stellenübersicht';
$headerLinkHref = 'index.php';

require __DIR__ . '/includes/header.php';

?>

<main class="legal-page">
    <p class="eyebrow">Rechtliche Hinweise</p>
    <h1>Impressum</h1>

    <section>
        <h2>Hinweis zum Projekt</h2>
        <p>
            Diese Website ist ein nicht kommerzielles Minimum Viable Product,
            das im Rahmen eines Hochschulprojekts entwickelt wurde.
            FiktivFit Studios ist ein fiktives Unternehmen. Die
            dargestellten Stellenangebote dienen ausschließlich zu
            Demonstrationszwecken.
        </p>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>