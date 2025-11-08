<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers;
use App\Models\Credit;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\SaleItem;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->label('Select Customer')
                    ->required()
                    ->searchable(['name', 'address'])
                    ->preload()
                    ->native(false)
                    ->relationship('customer', 'name')
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('total_cost', '0.00');
                    })
                    ->columnSpanFull()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('Customer Name')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('email')
                            ->email(),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->prefix('+256')
                            ->maxLength(9)
                            ->placeholder('712345678')
                            ->tel()
                            ->required(),
                        Forms\Components\Textarea::make('address')
                            ->label('Address')
                            ->rows('3')
                            ->columnSpanFull()
                            ->required(),
                    ]),

                Forms\Components\Repeater::make('orderItems')
                    ->schema([
                        Forms\Components\Select::make('medicine_id')
                            ->label('Select Medicine')
                            ->required()
                            ->searchable(['name', 'batch_no', 'description'])
                            ->preload()
                            ->options(function (callable $get) {
                                return Medicine::whereNotNull('stock_quantity')
                                    ->where('stock_quantity', '>', 0)
                                    ->get()
                                    ->mapWithKeys(function ($medicine) {
                                        return [
                                            $medicine->id => $medicine->name . ' (Stock: ' . $medicine->stock_quantity . ' ' . ($medicine->measurement_unit ?? 'units') . ') - Selling: UGX ' . number_format($medicine->selling_price, 2) . ' | Purchase: UGX ' . number_format($medicine->buying_price, 2)
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->placeholder('Search by medicine name, batch number, or description...')
                            ->helperText('Only medicines with available stock are shown')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, $get) {
                                if ($state) {
                                    $medicine = Medicine::find($state);
                                    $set('price', number_format($medicine->selling_price, 2));
                                    $set('buying_price', number_format($medicine->buying_price, 2));
                                    $total = $medicine->selling_price * $get('quantity');
                                    $set('total', number_format($total, 2));
                                    $set('medicine', $medicine);
                                    $set('medicine_name', $medicine->name);
                                }
                            }),

                        Forms\Components\TextInput::make('medicine_name')
                            ->label('Medicine Name')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn($get) => $get('medicine_id') !== null)
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('medicine')
                            ->dehydrated(false),

                        Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->step(0.25)
                                    ->minValue(0.25)
                                    ->maxValue(function ($get) {
                                        $medicineId = $get('medicine_id');
                                        if ($medicineId) {
                                            $medicine = Medicine::find($medicineId);
                                            return $medicine ? $medicine->stock_quantity : 0;
                                        }
                                        return 0;
                                    })
                                    ->default(1)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, $get) {
                                        $price = floatval(str_replace(',', '', $get('price') ?? 0));
                                        $quantity = floatval($state);
                                        $total = $price * $quantity;
                                        $set('total', number_format($total, 2));
                                    }),

                                Forms\Components\TextInput::make('buying_price')
                                    ->prefix('UGX ')
                                    ->mask(RawJs::make('$money($input)'))
                                    ->label('Purchase Price')
                                    ->readOnly()
                                    ->helperText('Original buying price of this medicine'),
                                Forms\Components\TextInput::make('price')
                                    ->prefix('UGX ')
                                    ->mask(RawJs::make('$money($input)'))
                                    ->label('Unit Selling Price')
                                    ->dehydrated(true), // Ensure inclusion in the data

                            ])->columns(2),

                        Forms\Components\TextInput::make('total')
                            ->prefix('UGX ')
                            ->mask(RawJs::make('$money($input)'))
                            ->label('Total Cost')
                            ->disabled()
                            ->dehydrated(true), // Ensure inclusion in the data
                    ])
                    ->collapsible()
                    ->collapsed(fn($operation) => $operation === 'create')
                    ->cloneable()
                    ->columnSpan(2)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Set $set) {
                        $totalCost = 0;
                        if (!empty($state)) {
                            $totalCost = collect($state)
                                ->sum(function ($item) {
                                    $price = floatval(str_replace(',', '', $item['price'] ?? 0));
                                    $quantity = floatval($item['quantity'] ?? 1);
                                    return $price * $quantity;
                                });
                        }
                        $set('total_cost', number_format($totalCost, 2));
                    }),


                Forms\Components\TextInput::make('total_cost')
                    ->prefix('UGX ')
                    ->mask(RawJs::make('$money($input)'))
                    ->label('Total Purchase Cost')
                    ->disabled()
                    ->columnSpanFull()
                    ->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_number')
                    ->prefix('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('UGX ')
                    ->searchable()
                    ->summarize(Sum::make()->money('UGX ')->label('Total')),
                Tables\Columns\SelectColumn::make('payment_status')
                    ->options([
                        'paid' => 'Paid',
                        'unpaid' => 'Unpaid',
                        'pending' => 'Pending',
                        'credit' => 'Credit',
                    ])
                    ->sortable()
                    ->disabled(fn($record) => in_array($record->payment_status, ['paid', 'credit'])) // Disable for paid & credit
                    ->afterStateUpdated(function ($state, $record, $livewire) {
                        Notification::make()
                            ->success()
                            ->title('Status Updated')
                            ->body("The payment status has been updated to: {$state}")
                            ->send();

                        if ($state === 'credit') {
                            $credit = new Credit();
                            $credit->customer_id = $record->customer_id;
                            $credit->order_number = $record->order_number;
                            $credit->amount_owed = $record->total_amount;
                            $credit->balance = $credit->amount_owed;
                            $credit->save();

                            return redirect()->route('filament.app.resources.credits.index');
                        }
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('print_receipt')
                        ->label('Receipt')
                        ->icon('heroicon-m-receipt-percent')
                        ->url(fn($record) => route('receipts.print', $record->id))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('print_invoice')
                        ->label(label: 'Invoice')
                        ->icon('heroicon-m-document-text')
                        ->url(url: fn($record) => route('invoice.print', $record->id))
                        ->openUrlInNewTab(),
                ])
                    ->label('Print')
                    ->icon('heroicon-m-printer')
                    ->color('success')
                    ->button(),
                Tables\Actions\EditAction::make(),
                // ->disabled(fn($record) => !in_array($record->payment_status, ['pending', 'unpaid']))
                // ->tooltip('Only pending or unpaid sales can be edited')
                // ->slideOver(),#

                Tables\Actions\DeleteAction::make()
                    ->modalDescription(function ($record) {
                        $saleItemsCount = SaleItem::where('sale_id', $record->id)->count();

                        if ($saleItemsCount > 0) {
                            return "⚠️ WARNING: This sale has {$saleItemsCount} sale item(s) that will be permanently deleted.\n\nThis action cannot be undone. All related sale items will be permanently deleted.";
                        }

                        return 'Are you sure you want to delete this sale? This action cannot be undone.';
                    })
                    ->modalHeading(function ($record) {
                        $saleItemsCount = SaleItem::where('sale_id', $record->id)->count();

                        if ($saleItemsCount > 0) {
                            return 'Delete Sale and Related Items?';
                        }

                        return 'Delete Sale?';
                    })
                    ->before(function ($record) {
                        SaleItem::where('sale_id', $record->id)->delete();
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Order deleted')
                            ->body('The order has been deleted successfully.'),
                    )
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalDescription('⚠️ WARNING: This will permanently delete all selected sales and their related sale items. This action cannot be undone.')
                        ->modalHeading('Delete Sales and Related Items?')
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                SaleItem::where('sale_id', $record->id)->delete();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
