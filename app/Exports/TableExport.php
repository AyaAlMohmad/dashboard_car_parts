<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class TableExport implements FromArray, WithTitle
{
    use Exportable;

    private string $modelClass;
    private string $title;
    private ?array $columns;

    public function __construct(string $modelClass, string $title, ?array $columns = null)
    {
        $this->modelClass = $modelClass;
        $this->title = $title;
        $this->columns = $columns;
    }

    public function array(): array
    {
        $model = new $this->modelClass;
        $query = $model->newQuery();
        $items = $query->get();

        $columns = $this->columns ?? $model->getFillable();

        $rows = [];
        $rows[] = $columns;
        foreach ($items as $item) {
            $row = [];
            foreach ($columns as $col) {
                $row[] = $item->{$col} ?? '';
            }
            $rows[] = $row;
        }
        return $rows;
    }

    public function title(): string
    {
        return $this->title;
    }
}
