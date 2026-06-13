<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCardRequest;
use App\Models\Card;
use App\Http\Resources\CardResource;
use App\Http\Resources\CardSummaryResource;

class CardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $cards = Card::with([
            'cardType',
            'assignedTo',
            'status'
        ])
        ->get();

        return CardSummaryResource::collection($cards);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCardRequest $request)
    {
        $validated_data = $request->validated();

        $card = Card::create($validated_data);

        $loadedCard = $card
            ->load([
                'cardType',
                'assignedTo',
                'module',
                'team',
                'platform',
                'project',
                'status',
                'creator'
            ]);

        return (new CardResource($loadedCard))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $card = Card::with([
            'cardType',
            'assignedTo',
            'module',
            'team',
            'platform',
            'project',
            'status',
            'creator'
        ])
        ->findOrFail($id);

        return new CardResource($card);
    }
}