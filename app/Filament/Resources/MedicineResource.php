<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MedicineResource\Pages;
use App\Filament\Resources\MedicineResource\RelationManagers;
use App\Models\Medicine;
use App\Models\PurchaseItem;
use App\Models\StockCategory;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class MedicineResource extends Resource
{
    protected static ?string $model = Medicine::class;

    protected static ?string $navigationIcon = 'heroicon-c-qr-code';

    protected static ?string $navigationGroup = 'Stock Management';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('stock_category_id')
                    ->label('Select Stock Category')
                    ->required()
                    ->native(false)
                    ->options(function () {
                        return StockCategory::all()->pluck('name', 'id')->mapWithKeys(function ($name, $id) {
                            return [$id => Str::ucfirst($name)];
                        });
                    })
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('Category Name')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Forms\Components\Select::make('supplier_id')
                    ->label('Select Supplier')
                    ->required()
                    ->searchable(['name', 'address', 'contact_person'])
                    ->preload()
                    ->native(false)
                    ->options(function () {
                        return Supplier::all()->pluck('name', 'id')->mapWithKeys(function ($name, $id) {
                            return [$id => Str::ucfirst($name)];
                        });
                    })
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->label("Supplier Name")
                            ->columnSpanFull()
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->nullable(),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->prefix('+256')
                            ->maxLength(9)
                            ->placeholder('712345678')
                            ->tel()
                            ->required(),
                        Forms\Components\TextInput::make('contact_person')
                            ->label('Contact Person')
                            ->columnSpanFull()
                            ->required(),
                        Forms\Components\Textarea::make('address')
                            ->label('Address')
                            ->rows('5')
                            ->columnSpanFull()
                            ->required(),
                    ])
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('name')
                    ->label('Medicine Name')
                    ->columnSpanFull()
                    ->required(),
                Forms\Components\TextInput::make('buying_price')
                    ->label('Buying Price')
                    ->prefix('UGX')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(',')
                    ->required(),
                Forms\Components\TextInput::make('selling_price')
                    ->label('Selling Price')
                    ->prefix('UGX')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(','),
                Forms\Components\Select::make('measurement_unit')
                    ->label('Measurement Unit')
                    ->options([
                        'pkts' => 'Pkts',
                        'vials' => 'Vials',
                        'btl' => 'Btl',
                        'pc' => 'Pc',
                        'amp' => 'Amp',
                        'supp' => 'Supp',
                        'pess' => 'Pess',
                        'dose' => 'Dose',
                        'tube' => 'Tube',
                    ])
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('batch_no')
                    ->label('Batch No')
                    ->unique(Medicine::class, column: 'batch_no', ignoreRecord: true)
                    ->required(),
                Forms\Components\DatePicker::make('expiry_date')
                    ->label('Expiry Date')
                    ->native(false)
                    ->minDate(now())
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->columnSpanFull()
                    ->rows(5),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->description(fn(Medicine $record) => 'Batch No.: ' . $record->batch_no)
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        return Str::title($record->name);
                    }),
                Tables\Columns\TextColumn::make('buying_price')
                    ->money('UGX ')
                    ->searchable(),
                Tables\Columns\TextColumn::make('selling_price')
                    ->money('UGX ')
                    ->searchable(),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Available Stock')
                    ->getStateUsing(function ($record) {
                        if ($record->stock_quantity == null || $record->stock_quantity <= 0) {
                            return 'Out of Stock';
                        } else {
                            return $record->stock_quantity . ' ' . $record->measurement_unit;
                        }
                    }),
                Tables\Columns\TextColumn::make('stockCategory.name')
                    ->label('Category')
                    ->getStateUsing(function ($record) {
                        return Str::title($record->stockCategory->name);
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->sortable()
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
                SelectFilter::make('categories')
                    ->relationship('stockCategory', 'name')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('suppliers')
                    ->relationship('supplier', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->slideOver()->modalWidth(MaxWidth::Medium),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ManageMedicines::route('/'),
        ];
    }
}
