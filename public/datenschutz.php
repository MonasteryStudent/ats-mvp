<?php

declare(strict_types=1);

$pageTitle = 'Datenschutz | FiktivFit Karriere';
$headerLinkLabel = 'Zur Stellenübersicht';
$headerLinkHref = 'index.php';

require __DIR__ . '/includes/header.php';

?>

<main class="legal-page">
    <p class="eyebrow">Rechtliche Hinweise</p>
    <h1>Datenschutz</h1>

    <section>
        <h2>Hinweis zum Projekt</h2>
        <p>
            Diese Website ist ein lokal ausgeführtes Minimum Viable Product
            für ein fiktives Applicant-Tracking-System. Sie dient
            ausschließlich zu Lehr- und Demonstrationszwecken. Für die
            Erprobung dürfen keine echten personenbezogenen Daten oder
            vertraulichen Dokumente verwendet werden.
        </p>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>