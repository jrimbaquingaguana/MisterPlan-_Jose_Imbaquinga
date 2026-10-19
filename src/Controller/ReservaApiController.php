<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Validation\ReservaFiltroValidator;
use App\Validation\ReservaIdValidator;
use Closure;

final class ReservaApiController
{
    public function __construct(private Closure $createService, private ReservaFiltroValidator $validator)
    {
    }

    public function index(array $input): void
    {
        header('Cache-Control: no-store');
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            JsonResponse::send(['error' => 'Filtros no válidos', 'errors' => $errors], 400);
            return;
        }
        $filters = array_intersect_key($input, array_flip(['estado', 'desde', 'hasta', 'q']));
        if (isset($filters['q'])) {
            $filters['q'] = trim($filters['q']);
        }
        $service = ($this->createService)();
        JsonResponse::send($service->list($filters, (int) ($input['page'] ?? '1'), (int) ($input['per_page'] ?? '10')));
    }

    public function show(string $id): void
    {
        header('Cache-Control: no-store');
        $validatedId = ReservaIdValidator::parse($id);
        $service = ($this->createService)();
        JsonResponse::send($service->detail($validatedId));
    }

    public function changeStatus(string $id, array $input): void
    {
        header('Cache-Control: no-store');
        $validatedId = ReservaIdValidator::parse($id);
        $errors = (new \App\Validation\ReservaValidator())->validateStatus($input);
        if ($errors !== []) {
            throw new \App\Domain\ReservaValidationException($errors);
        }
        $service = ($this->createService)();
        JsonResponse::send($service->changeStatus($validatedId, $input));
    }

    public function create(array $input): void
    {
        header('Cache-Control: no-store');
        $errors = (new \App\Validation\ReservaValidator())->validateCreate($input);
        if ($errors !== []) {
            JsonResponse::send(['error' => 'Datos de reserva no válidos', 'errors' => $errors], 422);
            return;
        }
        $service = ($this->createService)();
        $result = $service->create($input);
        JsonResponse::send($result, 201, ['Location' => '/api/reservas/' . $result['data']['id']]);
    }
}
