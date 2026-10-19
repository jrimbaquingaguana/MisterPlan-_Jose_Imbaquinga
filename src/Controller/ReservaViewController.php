<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\HtmlResponse;
use App\Validation\ReservaFiltroValidator;
use App\Validation\ReservaIdValidator;
use Closure;

final class ReservaViewController
{
    private const DEFAULTS = ['estado' => '', 'desde' => '', 'hasta' => '', 'q' => '', 'page' => '1', 'per_page' => '10'];

    public function __construct(private Closure $createService, private ReservaFiltroValidator $validator, private HtmlResponse $views)
    {
    }

    public function new(): void
    {
        $this->views->render('reservas/new.html.twig', [
            'api_url' => '/api/reservas', 'detail_base_url' => '/reservas/',
        ]);
    }

    public function index(array $input): void
    {
        $errors = $this->validator->validate($input);
        $values = self::DEFAULTS;
        foreach (self::DEFAULTS as $field => $default) {
            if (isset($input[$field]) && !isset($errors[$field])) {
                $values[$field] = $input[$field];
            }
        }
        $values['q'] = trim($values['q']);
        $options = array_unique([2, 10, 25, 50, 100, (int) $values['per_page']]);
        sort($options);
        $context = [
            'values' => $values, 'errors' => $errors, 'per_page_options' => $options,
            'reservas' => [], 'meta' => ['page' => (int) $values['page'], 'per_page' => (int) $values['per_page'], 'total' => 0, 'total_pages' => 0],
            'previous_url' => null, 'next_url' => null, 'valid_page_url' => null,
        ];
        if ($errors !== []) {
            $this->views->render('reservas/list.html.twig', $context, 400);
            return;
        }
        $service = ($this->createService)();
        $result = $service->list(array_intersect_key($values, array_flip(['estado', 'desde', 'hasta', 'q'])),
            (int) $values['page'], (int) $values['per_page']);
        $context['meta'] = $result['meta'];
        foreach ($result['data'] as $reserva) {
            $context['reservas'][] = $reserva + ['detail_url' => '/reservas/' . $reserva['id'] . '?' . $this->query($values)];
        }
        $page = $result['meta']['page'];
        $last = max(1, $result['meta']['total_pages']);
        if ($page > $last) {
            $context['valid_page_url'] = '/reservas?' . $this->query(array_replace($values, ['page' => $last]));
        } else {
            if ($page > 1) {
                $context['previous_url'] = '/reservas?' . $this->query(array_replace($values, ['page' => $page - 1]));
            }
            if ($page < $last) {
                $context['next_url'] = '/reservas?' . $this->query(array_replace($values, ['page' => $page + 1]));
            }
        }
        $this->views->render('reservas/list.html.twig', $context);
    }

    public function show(string $id, array $input): void
    {
        $validatedId = ReservaIdValidator::parse($id);
        if ($this->validator->validate($input) !== []) {
            $this->index($input);
            return;
        }
        $values = array_replace(self::DEFAULTS, $input);
        $values['q'] = trim($values['q']);
        $service = ($this->createService)();
        $this->views->render('reservas/show.html.twig', [
            'reserva' => $service->detail($validatedId)['data'],
            'back_url' => '/reservas?' . $this->query($values),
        ]);
    }

    private function query(array $values): string
    {
        return http_build_query($values, '', '&', PHP_QUERY_RFC3986);
    }
}
