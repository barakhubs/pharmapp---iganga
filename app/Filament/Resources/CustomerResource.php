<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Credit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Sales Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Customer Name'),
                Tables\Columns\TextColumn::make('phone')
                    ->prefix('+256 ')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('statements')
                    ->label('Statements')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('report_type')
                            ->label('Report Type')
                            ->required()
                            ->options([
                                'sales' => 'Sales Records',
                                'credits' => 'Credit Records',
                                'both' => 'Sales & Credits',
                            ])
                            ->default('sales')
                            ->native(false),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->default(now()->subMonth())
                            ->native(false)
                            ->maxDate(now()),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->maxDate(now())
                            ->after('start_date')
                            ->rule('after_or_equal:start_date'),
                        Forms\Components\Select::make('export_format')
                            ->label('Export Format')
                            ->required()
                            ->options([
                                'pdf' => 'PDF Report',
                                'csv' => 'CSV Spreadsheet',
                            ])
                            ->default('pdf')
                            ->native(false),
                    ])
                    ->action(function (array $data, $record) {
                        $url = route('customer.statements', [
                            'customer' => $record->id,
                            'report_type' => $data['report_type'],
                            'start_date' => $data['start_date'],
                            'end_date' => $data['end_date'],
                            'format' => $data['export_format'],
                        ]);

                        // Use JavaScript to open the URL in a new tab for download
                        return redirect()->to($url);
                    })
                    ->modalHeading(fn($record) => 'Generate Statement for ' . $record->name)
                    ->modalSubmitActionLabel('Generate Report')
                    ->modalWidth(MaxWidth::Medium),
                Tables\Actions\EditAction::make()->slideOver()->modalWidth(MaxWidth::Medium),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription(function ($record) {
                        $salesCount = Sale::where('customer_id', $record->id)->count();
                        $creditsCount = Credit::where('customer_id', $record->id)->count();

                        // Count sale items
                        $saleItemsCount = 0;
                        if ($salesCount > 0) {
                            $saleIds = Sale::where('customer_id', $record->id)->pluck('id');
                            $saleItemsCount = SaleItem::whereIn('sale_id', $saleIds)->count();
                        }

                        if ($salesCount > 0 || $creditsCount > 0 || $saleItemsCount > 0) {
                            $message = "⚠️ WARNING: This customer has related records that will be permanently deleted:\n\n";
                            if ($salesCount > 0) {
                                $message .= "• {$salesCount} sales record(s)\n";
                            }
                            if ($saleItemsCount > 0) {
                                $message .= "• {$saleItemsCount} sale item(s)\n";
                            }
                            if ($creditsCount > 0) {
                                $message .= "• {$creditsCount} credit record(s)\n";
                            }
                            $message .= "\nThis action cannot be undone. All related records will be permanently deleted.";
                            return $message;
                        }

                        return 'Are you sure you want to delete this customer? This action cannot be undone.';
                    })
                    ->modalHeading(function ($record) {
                        $salesCount = Sale::where('customer_id', $record->id)->count();
                        $creditsCount = Credit::where('customer_id', $record->id)->count();

                        // Count sale items
                        $saleItemsCount = 0;
                        if ($salesCount > 0) {
                            $saleIds = Sale::where('customer_id', $record->id)->pluck('id');
                            $saleItemsCount = SaleItem::whereIn('sale_id', $saleIds)->count();
                        }

                        if ($salesCount > 0 || $creditsCount > 0 || $saleItemsCount > 0) {
                            return 'Delete Customer and Related Records?';
                        }

                        return 'Delete Customer?';
                    })
                    ->before(function ($record) {
                        // Delete related records in correct order to avoid foreign key constraint violations

                        // First, get all sales for this customer
                        $sales = Sale::where('customer_id', $record->id)->get();

                        // Delete sale items first (they reference sales)
                        foreach ($sales as $sale) {
                            SaleItem::where('sale_id', $sale->id)->delete();
                        }

                        // Then delete sales (they reference customer)
                        Sale::where('customer_id', $record->id)->delete();

                        // Finally delete credits (they reference customer)
                        Credit::where('customer_id', $record->id)->delete();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalDescription('⚠️ WARNING: This will permanently delete all selected customers and their related records (sales and credits). This action cannot be undone.')
                        ->modalHeading('Delete Customers and Related Records?')
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                // Delete related records in correct order to avoid foreign key constraint violations

                                // First, get all sales for this customer
                                $sales = Sale::where('customer_id', $record->id)->get();

                                // Delete sale items first (they reference sales)
                                foreach ($sales as $sale) {
                                    SaleItem::where('sale_id', $sale->id)->delete();
                                }

                                // Then delete sales (they reference customer)
                                Sale::where('customer_id', $record->id)->delete();

                                // Finally delete credits (they reference customer)
                                Credit::where('customer_id', $record->id)->delete();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCustomers::route('/'),
        ];
    }
}
