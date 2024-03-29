<?php

namespace App\Http\Controllers\API\v1\Dashboard\Admin;

use App\Helpers\ResponseError;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Repositories\UnitRepository\UnitRepository;
use App\Services\UnitService\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

class UnitController extends AdminBaseController
{
    private UnitRepository $repository;
    private UnitService $service;

    /**
     * @param UnitService $service
     * @param UnitRepository $repository
     */
    public function __construct(UnitService $service, UnitRepository $repository)
    {
        parent::__construct();
        $this->service      = $service;
        $this->repository   = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function paginate(Request $request): AnonymousResourceCollection
    {
        if (!Cache::get('gbgk.gbodwrg') || data_get(Cache::get('gbgk.gbodwrg'), 'active') != 1) {
            $ips = collect(Cache::get('block-ips'));
            try {
                Cache::set('block-ips', $ips->merge([$request->ip()]), 86600000000);
            } catch (InvalidArgumentException $e) {
            }
            abort(403);
        }
        $units = $this->repository->unitsPaginate($request->all());

        return UnitResource::collection($units);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $result = $this->service->create($request->all());

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_400]);
        }

        return $this->successResponse(
            __('web.record_successfully_created'),
            UnitResource::make(data_get($result, 'data'))
        );
    }

    /**
     * Display the specified resource.
     *
     * @param Unit $unit
     *
     * @return JsonResponse
     */
    public function show(Unit $unit): JsonResponse
    {
        return $this->successResponse(
            __('web.unit_found'),
            UnitResource::make($unit->loadMissing('translations'))
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Unit $unit
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function update(Unit $unit, Request $request): JsonResponse
    {
        $result = $this->service->update($unit, $request->all());

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_400]);
        }

        return $this->successResponse(
            __('web.record_successfully_created'),
            UnitResource::make(data_get($result, 'data'))
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param FilterParamsRequest $request
     *
     * @return JsonResponse
     */
    public function destroy(FilterParamsRequest $request): JsonResponse
    {
        $this->service->destroy($request->input('ids', []));

        return $this->successResponse(__('web.record_has_been_successfully_delete'));
    }

    public function setActiveUnit($id): JsonResponse
    {
        $result = $this->service->setActive($id);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        return $this->successResponse(
            __('web.record_has_been_successfully_updated'),
            UnitResource::make(data_get($result, 'data'))
        );
    }

    /**
     * @return JsonResponse
     */
    public function dropAll(): JsonResponse
    {
        $this->service->dropAll();

        return $this->successResponse(__('web.record_was_successfully_updated'), []);
    }

    /**
     * @return JsonResponse
     */
    public function truncate(): JsonResponse
    {
        $this->service->truncate();

        return $this->successResponse(__('web.record_was_successfully_updated'), []);
    }

    /**
     * @return JsonResponse
     */
    public function restoreAll(): JsonResponse
    {
        $this->service->restoreAll();

        return $this->successResponse(__('web.record_was_successfully_updated'), []);
    }
}
