<?php

declare(strict_types=1);

function escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function formatDate(?string $value): string
{
    if ($value === null || $value === '') {
        return '–';
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? '–'
        : date('d.m.Y', $timestamp);
}

function statusLabel(string $status): string
{
    $labels = [
        'eingegangen' => 'Eingegangen',
        'vorauswahl' => 'Vorauswahl',
        'interview' => 'Interview',
        'angebot' => 'Angebot',
        'abgelehnt' => 'Abgelehnt',
        'zurueckgezogen' => 'Zurückgezogen',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function documentTypeLabel(string $documentType): string
{
    $labels = [
        'lebenslauf' => 'Lebenslauf',
        'anschreiben' => 'Anschreiben',
        'zeugnis' => 'Zeugnisse',
        'anlage' => 'Weitere Anlagen',
    ];

    return $labels[$documentType] ?? 'Dokument';
}

function formatFileSize(int $bytes): string
{
    return number_format(
        $bytes / 1024,
        0,
        ',',
        '.'
    ) . ' KB';
}