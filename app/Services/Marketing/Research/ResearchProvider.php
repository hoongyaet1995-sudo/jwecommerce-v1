<?php

namespace App\Services\Marketing\Research;

interface ResearchProvider
{
    public function search(string $query): array;
    public function extract(array $urls): array;
}
