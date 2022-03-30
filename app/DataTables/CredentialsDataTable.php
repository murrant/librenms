<?php

namespace App\DataTables;

use App\Models\Credential;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CredentialsDataTable extends DataTable
{
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('action', 'credentials.action');
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\Credential $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(Credential $model)
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html()
    {
        return $this->builder()
                    ->setTableId('credentials-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->parameters([
                        'bAutoWidth' => false,
                        'language' => [
                            'search' => '',
                            'searchPlaceholder' => __('Search')
                        ]
                    ])
                    ->dom('Bfrtip')
                    ->orderBy(1)
                    ->buttons(
                        Button::make('create'),
                        Button::make('export'),
                        Button::make('print'),
                        Button::make('reset'),
                        Button::make('reload')
                    );
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
            Column::make('id')->hidden(),
            Column::make('credential_type'),
            Column::make('description'),
            Column::make('created_at')->render('moment(data).format("lll")')->hidden(),
            Column::make('updated_at')->render('moment(data).format("lll")')->hidden(),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-center'),        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'Credentials_' . date('YmdHis');
    }
}
