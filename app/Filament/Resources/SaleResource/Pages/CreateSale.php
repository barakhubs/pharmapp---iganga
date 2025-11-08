<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\SaleItem;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected ?string $heading = 'Create New Sale';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $sale = new \App\Models\Sale();
            $sale->customer_id = $data['customer_id'];
            $sale->total_amount = 0.0; // Initialize with 0
            $sale->save();

            $total_cost = 0.0;

            // Check if orderItems exist and process them
            if (isset($data['orderItems']) && is_array($data['orderItems'])) {
                foreach ($data['orderItems'] as $item) {
                    $price = (float) str_replace(',', '', $item['price']);

                    \App\Models\SaleItem::create([
                        'sale_id' => $sale->id,
                        'medicine_id' => $item['medicine_id'],
                        'quantity' => $item['quantity'],
                        'price' => $price,
                        'total' => $price * $item['quantity'],
                    ]);

                    // Update medicine stock
                    $medicine = \App\Models\Medicine::find($item['medicine_id']);
                    if ($medicine) {
                        $medicine->update([
                            'stock_quantity' => $medicine->stock_quantity - $item['quantity']
                        ]);
                    }

                    $total_cost += ($item['quantity'] * $price);
                }
            }

            // Update the sale with the calculated total
            $sale->update([
                'total_amount' => $total_cost,
            ]);

            return $sale;
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('New sales record registered')
            ->body('A new sales record has been created successfully.');
    }
}
