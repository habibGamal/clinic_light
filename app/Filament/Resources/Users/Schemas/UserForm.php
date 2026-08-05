<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Role;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('معلومات أساسية')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('الاسم')
                                ->maxLength(255)
                                ->required(),
                            TextInput::make('email')
                                ->label('البريد الإلكتروني')
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->email()
                                ->required(),
                            TextInput::make('phone')
                                ->label('الهاتف')
                                ->tel()
                                ->maxLength(20),
                            Select::make('role_id')
                                ->label('الدور')
                                ->relationship('role', 'name')
                                ->required()
                                ->live()
                                ->preload(),
                        ]),
                    ]),

                Section::make('معلومات إضافية')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('specialization')
                                ->label('التخصص')
                                ->visible(fn (Get $get): bool => Role::query()->where('id', $get('role_id'))->value('name') === 'doctor'),
                            DatePicker::make('hire_date')
                                ->label('تاريخ التعيين')
                                ->visible(fn (Get $get): bool => Role::query()->where('id', $get('role_id'))->value('name') === 'technician'),
                        ]),
                    ]),

                Section::make('الأمان')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('password')
                                ->label('كلمة المرور')
                                ->password()
                                ->required(fn ($livewire): bool => $livewire instanceof CreateUser)
                                ->revealable(filament()->arePasswordsRevealable())
                                ->rule(Password::default())
                                ->autocomplete('new-password')
                                ->dehydrated(fn ($state): bool => filled($state))
                                ->dehydrateStateUsing(fn ($state): string => Hash::make($state)),
                            Toggle::make('is_active')
                                ->label('نشط')
                                ->default(true),
                        ]),
                    ]),
            ]);
    }
}
