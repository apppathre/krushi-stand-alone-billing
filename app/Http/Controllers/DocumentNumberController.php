<?php

namespace App\Http\Controllers;

use App\Services\InvoiceNumberGenerator;
use App\Support\TransactionTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * AJAX preview of Transaction Master numbers (does not increment).
 */
class DocumentNumberController extends Controller
{
    public function __construct(private InvoiceNumberGenerator $numbers) {}

    public function peek(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', '');
        abort_unless(array_key_exists($type, TransactionTypes::all()), 404);

        $formatId = $request->filled('format_id') ? (int) $request->query('format_id') : null;

        try {
            $peek = $this->numbers->peek($type, $request->query('date'), $formatId ?: null);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: __('Could not preview that number.'),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($peek);
    }
}
