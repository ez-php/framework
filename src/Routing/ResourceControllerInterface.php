<?php

declare(strict_types=1);

namespace EzPhp\Routing;

use EzPhp\Http\Request;
use EzPhp\Http\Response;

/**
 * Interface ResourceControllerInterface
 *
 * Contract for controllers registered via {@see Router::resource()}.
 * Implementing all seven methods ensures the router can wire up the full
 * RESTful route set without relying on dynamic dispatch.
 *
 * | Method | URI                      | Route name          |
 * |--------|--------------------------|---------------------|
 * | GET    | /{resource}              | {resource}.index    |
 * | GET    | /{resource}/create       | {resource}.create   |
 * | POST   | /{resource}              | {resource}.store    |
 * | GET    | /{resource}/{id}         | {resource}.show     |
 * | GET    | /{resource}/{id}/edit    | {resource}.edit     |
 * | PUT    | /{resource}/{id}         | {resource}.update   |
 * | DELETE | /{resource}/{id}         | {resource}.destroy  |
 *
 * @package EzPhp\Routing
 */
interface ResourceControllerInterface
{
    /**
     * Display a listing of the resource. (GET /{resource})
     *
     * @param Request $request
     *
     * @return Response|string
     */
    public function index(Request $request): Response|string;

    /**
     * Show the form for creating a new resource. (GET /{resource}/create)
     *
     * @param Request $request
     *
     * @return Response|string
     */
    public function create(Request $request): Response|string;

    /**
     * Store a newly created resource. (POST /{resource})
     *
     * @param Request $request
     *
     * @return Response|string
     */
    public function store(Request $request): Response|string;

    /**
     * Display the specified resource. (GET /{resource}/{id})
     *
     * @param Request $request
     *
     * @return Response|string
     */
    public function show(Request $request): Response|string;

    /**
     * Show the form for editing the specified resource. (GET /{resource}/{id}/edit)
     *
     * @param Request $request
     *
     * @return Response|string
     */
    public function edit(Request $request): Response|string;

    /**
     * Update the specified resource. (PUT /{resource}/{id})
     *
     * @param Request $request
     *
     * @return Response|string
     */
    public function update(Request $request): Response|string;

    /**
     * Remove the specified resource. (DELETE /{resource}/{id})
     *
     * @param Request $request
     *
     * @return Response|string
     */
    public function destroy(Request $request): Response|string;
}
