<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Models\AdminLog;

final class AdminLogController extends Controller
{
    public function index(array $params = []): void
    {
        $page = max(1, Request::integer('page', 1));

        $this->renderAdmin('admin/logs/index', [
            'title' => 'Logs administrativos',
            'logs' => AdminLog::paginateAll($page, 25),
        ]);
    }
}
