<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditResource\Pages;
use App\Filament\Resources\CreditResource\RelationManagers;
use App\Models\Credit;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CreditResource extends Resource
{
    protected static ?string $model = Credit::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Expenses & Credits';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Mode selector - hidden field to determine form behavior
                Forms\Components\Hidden::make('form_mode')
                    ->default('pay_credit'),

                // Customer selection for paying existing credits
                Forms\Components\Select::make('customer_id')
                    ->required()
                    ->label('Select Customer')
                    ->options(function (callable $get) {
                        $mode = $get('form_mode');
                        if ($mode === 'add_credit') {
                            // For new credits, show all customers
                            return Customer::pluck('name', 'id');
                        } else {
                            // For paying credits, show only customers with existing credits
                            return Customer::whereHas('credits')
                                ->pluck('name', 'id');
                        }
                    })
                    ->preload()
                    ->searchable()
                    ->columnSpanFull()
                    ->native(false)
                    ->reactive()
                    ->afterStateUpdated(
                        function ($state, callable $set, callable $get) {
                            $mode = $get('form_mode');

                            if ($state && $mode === 'pay_credit') {
                                // Get all credits for this customer with remaining balance
                                $customerCredits = Credit::where('customer_id', $state)
                                    ->where('balance', '>', 0)
                                    ->get();

                                $totalBalance = $customerCredits->sum('balance');
                                $set('balance', $totalBalance);

                                // Store all credit IDs for payment processing
                                $creditIds = $customerCredits->pluck('id')->toArray();
                                $set('credit_ids', json_encode($creditIds));
                            } else {
                                $set('balance', 0);
                                $set('credit_ids', null);
                            }
                        }
                    ),

                // For paying existing credits - show current balance
                Forms\Components\TextInput::make('balance')
                    ->readOnly()
                    ->prefix('UGX')
                    ->stripCharacters(',')
                    ->mask(RawJs::make('$money($input)'))
                    ->label('Current Balance')
                    ->visible(fn(callable $get) => $get('form_mode') === 'pay_credit')
                    ->columns(1),

                // Amount field - behaves differently for each mode
                Forms\Components\TextInput::make('amount_paid')
                    ->required()
                    ->label(fn(callable $get) => $get('form_mode') === 'add_credit' ? 'Credit Amount' : 'Amount Paid')
                    ->prefix('UGX')
                    ->stripCharacters(',')
                    ->numeric()
                    ->reactive()
                    ->minValue(0)
                    ->maxValue(fn($get) => $get('form_mode') === 'pay_credit' ? $get('balance') : null)
                    ->afterStateUpdated(
                        fn($state, callable $set, callable $get) =>
                        $get('form_mode') === 'pay_credit' && $state > $get('balance') ? $set('amount_paid', $get('balance')) : null
                    ),

                // Description field for new credits
                Forms\Components\Textarea::make('description')
                    ->label('Credit Description/Reason')
                    ->placeholder('Enter reason for this credit (e.g., Previous balance, Store credit, etc.)')
                    ->visible(fn(callable $get) => $get('form_mode') === 'add_credit')
                    ->columnSpanFull(),

                // Hidden fields
                Forms\Components\Hidden::make('credit_ids'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                Tables\Grouping\Group::make('customer.name')
                    ->label('Customer')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function ($record) {
                        $customerName = $record->customer->name;
                        $customerId = $record->customer_id;

                        // Get all credits for this customer
                        $customerCredits = \App\Models\Credit::where('customer_id', $customerId)->get();

                        $totalOwed = $customerCredits->sum('amount_owed');
                        $totalPaid = $customerCredits->sum('amount_paid');
                        $totalBalance = $customerCredits->sum('balance');
                        $recordCount = $customerCredits->count();

                        return "{$customerName} ({$recordCount} credits) - Total Owed: UGX " . number_format($totalOwed) .
                            " | Paid: UGX " . number_format($totalPaid) .
                            " | Balance: UGX " . number_format($totalBalance);
                    }),
            ])
            ->defaultGroup('customer.name')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->description)
                    ->placeholder('No description')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount_owed')
                    ->money('UGX ')
                    ->getStateUsing(fn($record) => $record->amount_owed + $record->amount_paid)
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->money('UGX ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance')
                    ->money('UGX ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn(string $state): string => match ($state) {
                        'partially_paid' => 'gray',
                        'paid' => 'success',
                        'pending' => 'warning',
                        'unpaid' => 'danger',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCredits::route('/'),
        ];
    }
}
