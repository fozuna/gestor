<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Helpers\CSRF;
use App\Helpers\Flash;
use App\Helpers\Session;
use App\Repositories\MembershipRepository;

final class WorkspaceController
{
    public function index(Request $request): void
    {
        Session::start();
        $uid = Session::get('user_id');
        $uid = is_int($uid) ? $uid : (int)$uid;
        $items = (new MembershipRepository())->listForUser($uid);
        if (count($items) === 1) {
            $m = $items[0];
            Session::set('tenant_id', (int)$m['tenant_id']);
            Session::set('tenant_name', (string)$m['tenant_name']);
            Session::set('role', (string)$m['role']);
            Response::redirect('/dashboard');
            return;
        }

        View::render('auth/workspaces', [
            'csrf' => CSRF::token(),
            'items' => $items,
            'error' => Flash::get('error'),
        ]);
    }

    public function select(Request $request): void
    {
        Session::start();
        $uid = Session::get('user_id');
        $uid = is_int($uid) ? $uid : (int)$uid;
        $tenantId = (int)($request->input('tenant_id') ?? 0);
        $role = (new MembershipRepository())->findRole($uid, $tenantId);
        if (!$role) {
            Flash::set('error', 'Workspace inválido.');
            Response::redirect('/workspaces');
            return;
        }

        $items = (new MembershipRepository())->listForUser($uid);
        $name = null;
        foreach ($items as $i) {
            if ((int)$i['tenant_id'] === $tenantId) {
                $name = (string)$i['tenant_name'];
                break;
            }
        }

        Session::set('tenant_id', $tenantId);
        Session::set('tenant_name', $name ?? '');
        Session::set('role', $role);
        Response::redirect('/dashboard');
    }
}

