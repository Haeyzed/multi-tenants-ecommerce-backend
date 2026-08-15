<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Landlord\NotificationTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * CRUD and lookup for central notification templates.
 */
class NotificationTemplateService
{
    public function __construct(private readonly TemplateRenderer $renderer) {}

    /**
     * @param  array{search?: string|null, is_active?: bool|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, NotificationTemplate>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $perPage = (int) ($params['per_page'] ?? 15);

        return NotificationTemplate::query()
            ->filter($params)
            ->orderBy('key')
            ->paginate($perPage);
    }

    public function findByKey(string $key): ?NotificationTemplate
    {
        return NotificationTemplate::query()->where('key', $key)->first();
    }

    public function findActiveByKey(string $key): ?NotificationTemplate
    {
        return NotificationTemplate::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): NotificationTemplate
    {
        $this->validateTemplateContent($data);

        return NotificationTemplate::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(NotificationTemplate $template, array $data): NotificationTemplate
    {
        $merged = array_merge($template->toArray(), $data);
        $this->validateTemplateContent($merged);

        $template->fill($data)->save();

        return $template->refresh();
    }

    public function delete(NotificationTemplate $template): void
    {
        $template->delete();
    }

    /**
     * Preview rendered template content without sending.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function preview(NotificationTemplate $template, array $data = []): array
    {
        /** @var list<string> $variables */
        $variables = $template->variables ?? [];

        return [
            'title' => $this->renderer->render($template->title, $data, $variables),
            'body' => $this->renderer->render($template->body, $data, $variables),
            'email_subject' => $this->renderer->render($template->email_subject, $data, $variables),
            'email_body' => $this->renderer->render($template->email_body, $data, $variables),
            'push_title' => $this->renderer->render($template->push_title, $data, $variables),
            'push_body' => $this->renderer->render($template->push_body, $data, $variables),
            'sms_body' => $this->renderer->render($template->sms_body, $data, $variables),
        ];
    }

    /**
     * @return Collection<int, array{label: string, value: string}>
     */
    public function options(array $params = []): Collection
    {
        return NotificationTemplate::query()
            ->filter($params)
            ->orderBy('name')
            ->get(['key', 'name'])
            ->map(fn (NotificationTemplate $template): array => [
                'label' => $template->name,
                'value' => $template->key,
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateTemplateContent(array $data): void
    {
        /** @var list<string> $variables */
        $variables = array_values(array_map('strval', $data['variables'] ?? []));

        $this->renderer->validateFields([
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'email_subject' => $data['email_subject'] ?? null,
            'email_body' => $data['email_body'] ?? null,
            'push_title' => $data['push_title'] ?? null,
            'push_body' => $data['push_body'] ?? null,
            'sms_body' => $data['sms_body'] ?? null,
        ], $variables);
    }
}
