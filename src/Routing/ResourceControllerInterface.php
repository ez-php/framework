<?php

declare(strict_types=1);

namespace EzPhp\Routing;

use EzPhp\Http\Request;
use EzPhp\Http\ResponseInterface;

/**
 * Interface ResourceControllerInterface
 *
 * Contract for controllers registered via {@see Router::resource()}.
 * Implementing all seven methods ensures the router can wire up the full
 * RESTful route set without relying on dynamic dispatch.
 *
 * Return types are ResponseInterface so a resource action may stream;
 * implementations may keep declaring the narrower Response.
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
     * @return ResponseInterface|string
     */
    public function index(Request $request): ResponseInterface|string;

    /**
     * Show the form for creating a new resource. (GET /{resource}/create)
     *
     * @param Request $request
     *
     * @return ResponseInterface|string
     */
    public function create(Request $request): ResponseInterface|string;

    /**
     * Store a newly created resource. (POST /{resource})
     *
     * @param Request $request
     *
     * @return ResponseInterface|string
     */
    public function store(Request $request): ResponseInterface|string;

    /**
     * Display the specified resource. (GET /{resource}/{id})
     *
     * @param Request $request
     *
     * @return ResponseInterface|string
     */
    public function show(Request $request): ResponseInterface|string;

    /**
     * Show the form for editing the specified resource. (GET /{resource}/{id}/edit)
     *
     * @param Request $request
     *
     * @return ResponseInterface|string
     */
    public function edit(Request $request): ResponseInterface|string;

    /**
     * Update the specified resource. (PUT /{resource}/{id})
     *
     * @param Request $request
     *
     * @return ResponseInterface|string
     */
    public function update(Request $request): ResponseInterface|string;

    /**
     * Remove the specified resource. (DELETE /{resource}/{id})
     *
     * @param Request $request
     *
     * @return ResponseInterface|string
     */
    public function destroy(Request $request): ResponseInterface|string;
}
