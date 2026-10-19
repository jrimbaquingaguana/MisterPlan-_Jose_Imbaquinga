<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\ReservaNotFoundException;
use App\Http\JsonResponse;
use App\Validation\ReservaFiltroValidator;
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
        $digits = ltrim($id, '0');
        if (preg_match('/\A[0-9]+\z/', $id) !== 1 || $digits === '' || strlen($digits) > 10
            || (strlen($digits) === 10 && strcmp($digits, '4294967295') > 0)) {
            throw new ReservaNotFoundException();
        }
        $service = ($this->createService)();
        JsonResponse::send($service->detail((int) $digits));
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
