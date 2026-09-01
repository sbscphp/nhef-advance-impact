<?php

namespace App\Http\Controllers\v1\Admin\Communications;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Communications\ConstituentSegmentListRequest;
use App\Http\Resources\Communications\ConstituentResource;
use App\Responser\JsonResponser;
use App\Services\Communications\ConstituentPickerService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Backs the "Select a donor" picker shared by the mail composer and call-log form. */
class ConstituentPickerController extends Controller
{
    public function __construct(private readonly ConstituentPickerService $constituentPickerService) {}

    public function index(ConstituentSegmentListRequest $request)
    {
        try {
            $paginator = $this->constituentPickerService->search($request->validated());

            $payload = $paginator->toArray();
            /** @var AnonymousResourceCollection $resource */
            $resource = ConstituentResource::collection($paginator);
            $payload['data'] = $resource->resolve();

            return JsonResponser::send(false, 'Constituents retrieved.', $payload);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\ConstituentPickerController@index');
        }
    }
}
