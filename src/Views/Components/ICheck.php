<?php

namespace AbuDawud\AlCrudLaravel\Views\Components;

use Illuminate\View\Component;

class ICheck extends Component
{
    public $name;
    public $value;
    public $title;


    public function __construct($name = '', $title = '', $value = '') {
        $this->title = $title;
        $this->name = $name;
        $this->value = $value;
    }

    public function render()
    {
        return view('alcrud::components.i-check');
    }
}

