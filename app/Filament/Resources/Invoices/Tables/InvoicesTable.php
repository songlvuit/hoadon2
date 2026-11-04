<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\DraftStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Invoices\Actions\CustomerLinkAction;
use App\Models\Invoice;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Mã hồ sơ')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->description(fn (Invoice $r) => $r->created_at?->format('d/m/Y')),
                TextColumn::make('program_name')
                    ->label('Chương trình')
                    ->searchable(['program_name', 'event_location'])
                    ->sortable()
                    ->wrap()
                    ->placeholder('—')
                    ->description(fn (Invoice $r) => collect([$r->eventDateLabel(), $r->event_location])->filter()->implode(' · ')),
                TextColumn::make('event_date')
                    ->label('Ngày thực hiện')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('company_name')
                    ->label('Đơn vị mua hàng')
                    ->searchable(['company_name', 'tax_code', 'quote_ref', 'contract_number'])
                    ->sortable()
                    ->wrap()
                    ->description(fn (Invoice $r) => collect(['MST '.$r->tax_code, $r->quote_ref, $r->contract_number ? 'HĐ '.$r->contract_number : null])->filter(fn ($v) => $v && $v !== 'MST ')->implode(' · ')),
                TextColumn::make('total')
                    ->label('Tổng tiền')
                    ->sortable()
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Money::vnd($state)),
                TextColumn::make('stage')
                    ->label('Bước hiện tại')
                    ->badge()
                    ->state(fn (Invoice $r) => $r->stageLabel())
                    ->color(fn (Invoice $r) => $r->stageColor()),
                TextColumn::make('draft_status')
                    ->label('Nháp')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('final_number')
                    ->label('Số HĐ final')
                    ->placeholder('—')
                    ->searchable()
                    ->fontFamily('mono')
                    ->description(fn (Invoice $r) => $r->final_date?->format('d/m/Y'))
                    ->toggleable(),
                TextColumn::make('payment_status')
                    ->label('Thanh toán')
                    ->badge()
                    ->sortable(),
                TextColumn::make('remaining')
                    ->label('Còn phải thu')
                    ->alignEnd()
                    ->state(fn (Invoice $r) => $r->remaining())
                    ->formatStateUsing(fn ($state) => $state > 0 ? Money::vnd($state) : '—')
                    ->color(fn (Invoice $r) => $r->payment_due_date?->isPast() && $r->remaining() > 0 ? 'danger' : null)
                    ->description(fn (Invoice $r) => $r->payment_due_date && $r->remaining() > 0 ? 'Hạn '.$r->payment_due_date->format('d/m/Y') : null),
                TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('event_date')
                    ->label('Ngày thực hiện')
                    ->schema([
                        DatePicker::make('from')->label('Thực hiện từ ngày')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Đến ngày')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('event_date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('event_date', '<=', $d)))
                    ->indicateUsing(fn (array $data) => collect([
                        ($data['from'] ?? null) ? 'Từ '.\Illuminate\Support\Carbon::parse($data['from'])->format('d/m/Y') : null,
                        ($data['until'] ?? null) ? 'Đến '.\Illuminate\Support\Carbon::parse($data['until'])->format('d/m/Y') : null,
                    ])->filter()->values()->all()),
                SelectFilter::make('draft_status')
                    ->label('Tình trạng nháp')
                    ->options(DraftStatus::class),
                SelectFilter::make('payment_status')
                    ->label('Thanh toán')
                    ->options(PaymentStatus::class),
                TernaryFilter::make('has_final')
                    ->label('Hoá đơn final')
                    ->trueLabel('Đã có final')
                    ->falseLabel('Chưa có final')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('final_file'),
                        false: fn (Builder $q) => $q->whereNull('final_file'),
                    ),
                TernaryFilter::make('has_contract')
                    ->label('Hợp đồng')
                    ->trueLabel('Đã có file hợp đồng')
                    ->falseLabel('Chưa có file hợp đồng')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('contract_files')->whereRaw("CAST(contract_files AS TEXT) <> '[]'"),
                        false: fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('contract_files')->orWhereRaw("CAST(contract_files AS TEXT) = '[]'")),
                    ),
                TernaryFilter::make('overdue')
                    ->label('Quá hạn thanh toán')
                    ->trueLabel('Chỉ hồ sơ quá hạn')
                    ->falseLabel('Không quá hạn')
                    ->queries(
                        true: fn (Builder $q) => $q->where('payment_status', '!=', PaymentStatus::Paid)->whereDate('payment_due_date', '<', now()),
                        false: fn (Builder $q) => $q->where(fn ($w) => $w->where('payment_status', PaymentStatus::Paid)->orWhereNull('payment_due_date')->orWhereDate('payment_due_date', '>=', now())),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    CustomerLinkAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
