<?php

namespace App\Policies;

use App\Models\User;

/**
 * Regra comum do painel: só administradores gerenciam a agenda e os
 * cadastros. Cada model tem a sua policy (descoberta pelo nome), que herda
 * daqui. Se um dia houver login de profissional, a regra específica entra
 * na policy do model, sem mexer nos controllers.
 */
abstract class PolicyDeAdministrador
{
    public function viewAny(User $user): bool
    {
        return $user->administrador;
    }

    public function create(User $user): bool
    {
        return $user->administrador;
    }

    public function update(User $user): bool
    {
        return $user->administrador;
    }

    public function delete(User $user): bool
    {
        return $user->administrador;
    }
}
