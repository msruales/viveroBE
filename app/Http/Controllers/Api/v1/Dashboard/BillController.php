<?php

namespace App\Http\Controllers\Api\v1\Dashboard;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Dashboard\StoreBillRequest;
use App\Http\Requests\Dashboard\UpdateBillRequest;
use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Http\Request;


class BillController extends ApiController
{

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $status = $request->query('status') ? $request->query('status') : 'active';

        $search = $request->query('search') ? $request->query('search') : '';
        $column_filter = $request->query('type_search') ? $request->query('type_search') : 'full_name';
        $date = $request->query('date') ? $request->query('date') : '';
        $per_page = $request->query('per_page') ? $request->query('per_page') : '10';

        $bills = Bill::with('client')
            ->with('details')
            ->when($status === 'active', function ($query) use ($search, $date, $column_filter) {
                $query->when($date !== '', function ($query) use ($date) {
                    return $query->whereDate('date', $date);
                })
                    ->whereRelation('client', $column_filter, 'LIKE', "%$search%");
            })
            ->when($status === 'all', function ($query) use ($search, $date, $column_filter) {
                $query->withTrashed()->when($date !== '', function ($query) use ($date) {
                    return $query->whereDate('date', $date);
                })
                    ->whereRelation('client', $column_filter, 'LIKE', "%$search%");
            })
            ->when($status === 'deleted', function ($query) use ($search, $date, $column_filter) {
                $query->onlyTrashed()->when($date !== '', function ($query) use ($date) {
                    return $query->whereDate('date', $date);
                })
                    ->whereRelation('client', $column_filter, 'LIKE', "%$search%");
            })
            ->orderBy('id', 'desc')
            ->paginate($per_page);

        $pagination = $this->parsePaginationJson($bills);

        return $this->successResponse([
            'filter' => $column_filter,
            'pagination' => $pagination,
            'bills' => $bills->items(),
        ]);
    }

    public function store(StoreBillRequest $request): \Illuminate\Http\JsonResponse
    {

        $data = $request->validated();

        $date_now = Carbon::now();

        $data['date'] = $date_now;
        $data['user_id'] = $request->user()->id;

        $new_bill = Bill::create($data);

        $new_bill->details()->createMany($data['details']);

        $new_bill->load('details');

        $new_bill->load('client');
        $new_bill->load('user');

        return $this->successResponse([
            'bill' => $new_bill,
        ]);
    }

    public function show(Bill $bill): \Illuminate\Http\JsonResponse
    {
        return $this->successResponse([
            'bill' => $bill
        ]);
    }

    public function update(UpdateBillRequest $request, Bill $bill)
    {
//        $data = $request->validated();
//
//        $date_now = Carbon::now();
//
//        $data['date'] = $date_now;
//
//        $new_bill = $bill->update($data);
//        $new_bill->details()->updateMany($data['details']);
//        $new_bill->load('details');
//        return $this->successResponse([
//            'bill' =>$new_bill,
//        ]);
    }

    public function destroy(Bill $bill): \Illuminate\Http\JsonResponse
    {
        if (!$bill->delete()) {
            return $this->errorResponse();
        }
        return $this->successResponse();
    }

    public function restore($id): \Illuminate\Http\JsonResponse
    {
        $category = Bill::withTrashed()->findOrFail($id);

        if (!$category->restore()) {
            return $this->errorResponse();
        }

        return $this->successResponse();
    }

    public function lastSales(): \Illuminate\Http\JsonResponse
    {

        $currentYear = Carbon::now()->year;

        $lastSales = Bill::withoutTrashed()->
        select('total', 'date')
            ->whereYear('date', $currentYear)
            ->get()
            ->groupBy(function ($val) {
                return Carbon::parse($val->date)->format('m');
            })
            ->map(function ($month) {
                return [
                    'sale' => $month->count(),
                    'total' => $month->sum('total')
                ];
            });

        return $this->successResponse($lastSales);
    }

}
