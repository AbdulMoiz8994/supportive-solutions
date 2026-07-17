<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AiAgentUserService
{
    /**
     * Create or refresh the platform user that represents this agent.
     */
    public function provision(AiAgent $agent): User
    {
        $email = $this->agentEmail($agent);

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $agent->name,
                'role' => User::ROLE_AI_AGENT,
                'organization_id' => $agent->organization_id,
                'password' => Hash::make(Str::random(48)),
                'is_active' => $agent->is_enabled && ! $agent->is_paused,
            ],
        );

        if ($agent->user_id !== $user->id) {
            $agent->update(['user_id' => $user->id]);
        }

        $this->syncPermissions($agent->fresh(['user']));

        return $user;
    }

    public function syncPermissions(AiAgent $agent): void
    {
        if (! $agent->user) {
            return;
        }

        // Permissions are evaluated via ai_agents.permission_slugs in User::hasPermission().
        $agent->user->update([
            'role' => User::ROLE_AI_AGENT,
            'is_active' => $agent->is_enabled && ! $agent->is_paused,
        ]);
    }

    /**
     * Issue a Sanctum token for runtime/API access (shown once to admins).
     */
    public function regenerateApiToken(AiAgent $agent): string
    {
        $user = $agent->user ?? $this->provision($agent);

        $user->tokens()->where('name', 'agent-runtime')->delete();

        return $user->createToken('agent-runtime', $agent->permission_slugs ?? [])->plainTextToken;
    }

    protected function agentEmail(AiAgent $agent): string
    {
        return sprintf(
            'agent.%s.org%d@agents.beydountech.internal',
            $agent->slug,
            $agent->organization_id
        );
    }
}
