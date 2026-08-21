<?php

namespace App\View\Components\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Shell extends Component
{
    public function __construct(
        public string $active = 'dashboard',
        public string $eyebrow = 'Administration',
        public string $title = 'Admin',
        public ?string $actionHref = null,
        public ?string $actionLabel = null,
        public bool $search = false,
        public string $query = '',
    ) {
    }

    public function render(): View
    {
        return view('components.admin.shell');
    }
}
