<?php

namespace AbuDawud\AlCrudLaravel\Views\Components;

use Illuminate\View\Component;

class Modal extends Component
{
    public $title;

    public $tableId;

    public function __construct($title = '', $tableId = '#datatable') {
        $this->title = $title;
        $this->tableId = $tableId;
    }

    public function render()
    {
        return view('alcrud::components.modal-crud');
    }
}
