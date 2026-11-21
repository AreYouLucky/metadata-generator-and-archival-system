<?php

namespace App\Exporters;

use App\Models\Record;

interface Exporter
{
    public function export(Record $record, ExportOptions $options): string;

    public function contentType(): string;

    public function extension(): string;
}
