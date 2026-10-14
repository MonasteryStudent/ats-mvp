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
        <p>
            Das FiktivFit-Bewerbungsportal ist ein lokal betriebenes 
            Hochschulprojekt für ein fiktives Fitnessunternehmen. Es dient
            ausschließlich zu Lehr- und Demonstrationszwecken. Für die
            Erprobung dürfen keine echten personenbezogenen Daten oder
            vertraulichen Dokumente verwendet werden.
        </p>

        <p>
            Das System speichert Kontodaten, Bewerbungsangaben und hochgeladene
            Unterlagen zur Demonstration des Bewerbungsprozesses. Die Telefonnummer
            ist optional. Bewerbende verwalten ausschließlich ihre eigenen Daten;
            Recruiting bearbeitet eingereichte Bewerbungen. Der Admin verwaltet
            Recruitingkonten. Die Speicherung erfolgt lokal.
        </p>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>