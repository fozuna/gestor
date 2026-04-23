<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Session;
use App\Repositories\MembershipRepository;
use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly MembershipRepository $memberships = new MembershipRepository()
    ) {
    }

    public function login(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if (!$user) {
            return false;
        }
        if (!password_verify($password, (string)$user['password_hash'])) {
            return false;
        }

        Session::regenerate();
        Session::set('user_id', (int)$user['id']);

        $memberships = $this->memberships->listForUser((int)$user['id']);
        if (count($memberships) === 1) {
            $m = $memberships[0];
            Session::set('tenant_id', (int)$m['tenant_id']);
            Session::set('tenant_name', (string)$m['tenant_name']);
            Session::set('role', (string)$m['role']);
        } else {
            Session::remove('tenant_id');
            Session::remove('tenant_name');
            Session::remove('role');
        }

        return true;
    }

    public function logout(): void
    {
        Session::destroy();
    }
}

