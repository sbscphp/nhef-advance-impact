<?php

namespace App\Services\ConstituencyType;

use App\Enums\AuditActionEnum;
use App\Enums\ePermission;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\ConstituencyType;
use App\Models\User;
use App\Repositories\Contracts\ConstituencyType\ConstituencyTypeRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ConstituencyTypeService
{
    public function __construct(
        private readonly ConstituencyTypeRepositoryInterface $typeRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): ConstituencyType
    {
        $this->assertPermission($actor, ePermission::CONSTITUENCY_TYPES_CREATE);

        $type = $this->typeRepository->create([
            'name' => $payload['name'],
            'is_active' => true,
            'created_by' => $actor->uuid,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CONSTITUENCY_TYPE_CREATED,
            $request,
            $actor->uuid,
            ['type_uuid' => $type->uuid, 'name' => $type->name],
            $actor->displayName().' created a constituency type: '.$type->name.'.',
            ConstituencyType::class,
            $type->uuid,
            ModuleEnums::constituency_type,
            201,
        );

        return $type;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->typeRepository->paginateForAdmin($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, ConstituencyType>, 1: bool}
     */
    public function exportForAdmin(array $filters): array
    {
        return $this->typeRepository->exportForAdmin($filters);
    }

    public function findForAdmin(string $uuid): ConstituencyType
    {
        $type = $this->typeRepository->findByUuid($uuid);

        if (! $type instanceof ConstituencyType) {
            throw new ApiException('Constituency type not found.', 404);
        }

        return $type;
    }

    /**
     * Same as {@see self::findForAdmin()} but attaches creator info and usage count as transient,
     * non-column attributes for ConstituencyTypeDetailResource to read; never call this on a
     * ConstituencyType that will be saved afterwards.
     */
    public function detailForAdmin(string $uuid): ConstituencyType
    {
        $type = $this->findForAdmin($uuid);
        $type->loadMissing(['creator.roles:id,name']);
        $type->setAttribute('usage_count', $this->typeRepository->usageCount($type));

        return $type;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $uuid, array $payload, Admin $actor, Request $request): ConstituencyType
    {
        $this->assertPermission($actor, ePermission::CONSTITUENCY_TYPES_UPDATE);

        $type = $this->findForAdmin($uuid);
        $previousName = $type->name;

        if ($payload['name'] !== $previousName) {
            $type = $this->typeRepository->update($type, ['name' => $payload['name']]);

            GeneralHelper::storeAuditLog(
                UserTypeEnum::ADMIN,
                AuditActionEnum::CONSTITUENCY_TYPE_UPDATED,
                $request,
                $actor->uuid,
                ['type_uuid' => $type->uuid, 'previous_name' => $previousName, 'name' => $type->name],
                $actor->displayName().' updated a constituency type: '.$previousName.' -> '.$type->name.'.',
                ConstituencyType::class,
                $type->uuid,
                ModuleEnums::constituency_type,
                200,
            );
        }

        return $type;
    }

    public function toggleActiveStatus(string $uuid, Admin $actor, Request $request): ConstituencyType
    {
        $this->assertPermission($actor, ePermission::CONSTITUENCY_TYPES_UPDATE);

        $type = $this->findForAdmin($uuid);
        $isActive = ! (bool) $type->is_active;

        $type = $this->typeRepository->update($type, ['is_active' => $isActive]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CONSTITUENCY_TYPE_STATUS_TOGGLED,
            $request,
            $actor->uuid,
            ['type_uuid' => $type->uuid, 'name' => $type->name, 'new_status' => $isActive ? 'active' : 'inactive'],
            $actor->displayName().($isActive ? ' reactivated' : ' deactivated').' a constituency type: '.$type->name.'.',
            ConstituencyType::class,
            $type->uuid,
            ModuleEnums::constituency_type,
            200,
        );

        return $type;
    }

    public function delete(string $uuid, Admin $actor, Request $request): void
    {
        $this->assertPermission($actor, ePermission::CONSTITUENCY_TYPES_DELETE);

        $type = $this->findForAdmin($uuid);
        $typeUuid = $type->uuid;
        $typeName = $type->name;

        $this->typeRepository->delete($type);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CONSTITUENCY_TYPE_DELETED,
            $request,
            $actor->uuid,
            ['type_uuid' => $typeUuid, 'name' => $typeName],
            $actor->displayName().' deleted a constituency type: '.$typeName.'.',
            ConstituencyType::class,
            $typeUuid,
            ModuleEnums::constituency_type,
            200,
        );
    }

    /**
     * Confers one or more constituency types on a constituent. Institution-admin-only: this is a
     * manual tag an institution's own admin confers on their own constituent, never NHEF. Gated
     * by `constituents.update` at the route level (shared by both scopes), so this permission
     * check is the actual enforcement, not just a backstop.
     *
     * @param  list<string>  $typeUuids
     */
    public function confer(string $userUuid, array $typeUuids, Admin $actor, Request $request): User
    {
        $this->assertPermission($actor, ePermission::CONSTITUENCY_TYPES_UPDATE);

        $user = $this->findUserForAdmin($userUuid);
        $types = $this->typeRepository->findManyByUuids($typeUuids)->where('is_active', true);

        if ($types->isEmpty()) {
            throw new ApiException('None of the selected constituency types are active.', 422);
        }

        $this->typeRepository->attachToUser($user, $types, $actor->uuid);
        $user->load('constituencyTypes');

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CONSTITUENT_TYPE_CONFERRED,
            $request,
            $actor->uuid,
            ['user_uuid' => $user->uuid, 'name' => $user->displayName(), 'types' => $types->pluck('name')->values()->all()],
            $actor->displayName().' conferred constituency type(s) on '.$user->displayName().': '.$types->pluck('name')->implode(', ').'.',
            User::class,
            $user->uuid,
            ModuleEnums::constituent_management,
            200,
        );

        return $user;
    }

    public function revoke(string $userUuid, string $typeUuid, Admin $actor, Request $request): User
    {
        $this->assertPermission($actor, ePermission::CONSTITUENCY_TYPES_UPDATE);

        $user = $this->findUserForAdmin($userUuid);
        $type = $this->findForAdmin($typeUuid);

        $this->typeRepository->detachFromUser($user, $type);
        $user->load('constituencyTypes');

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CONSTITUENT_TYPE_REVOKED,
            $request,
            $actor->uuid,
            ['user_uuid' => $user->uuid, 'name' => $user->displayName(), 'type' => $type->name],
            $actor->displayName().' revoked a constituency type from '.$user->displayName().': '.$type->name.'.',
            User::class,
            $user->uuid,
            ModuleEnums::constituent_management,
            200,
        );

        return $user;
    }

    private function findUserForAdmin(string $uuid): User
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (! $user instanceof User) {
            throw new ApiException('Constituent not found.', 404);
        }

        return $user;
    }

    /**
     * The master type list is shared platform-wide and readable by both scopes (gated by
     * constituency_types.read at the route level), but every mutation - create/edit/deactivate/
     * delete a type, or confer/revoke one on a constituent - requires its own constituency_types.*
     * permission, which only Institution Admin holds (see RolesAndPermissionsSeeder). Permission-
     * based rather than scope-based so the frontend can read this straight from `permissions`,
     * same treatment as CampaignService::create()/createNationalGivingDay().
     */
    private function assertPermission(Admin $actor, ePermission $permission): void
    {
        if (! $actor->checkPermissionTo($permission->value)) {
            throw new ApiException('Only an institution admin can manage constituency types.', 403);
        }
    }
}
