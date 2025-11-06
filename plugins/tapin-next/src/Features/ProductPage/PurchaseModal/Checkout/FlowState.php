<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Checkout;

final class FlowState
{
    /** @var array<int,array<string,mixed>> */
    private array $pendingAttendees = [];

    /** @var array<int,array<string,mixed>> */
    private array $attendeeQueue = [];

    private bool $processingSplitAdd = false;
    private bool $redirectNextAdd = false;

    /**
     * @param array<int,array<string,mixed>> $attendees
     */
    public function primeAttendees(array $attendees): void
    {
        $normalized = array_values(array_filter($attendees, 'is_array'));
        $this->pendingAttendees = $normalized;
        $this->attendeeQueue = $normalized;
    }

    public function resetAttendees(): void
    {
        $this->pendingAttendees = [];
        $this->attendeeQueue = [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function shiftNextAttendee(): ?array
    {
        if ($this->attendeeQueue !== []) {
            $next = array_shift($this->attendeeQueue);
            if (is_array($next)) {
                return $next;
            }
        }

        if ($this->pendingAttendees !== []) {
            $next = array_shift($this->pendingAttendees);
            if (is_array($next)) {
                return $next;
            }
        }

        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function drainQueue(): array
    {
        $remaining = $this->attendeeQueue;
        $this->attendeeQueue = [];
        return $remaining;
    }

    public function clearPendingAttendees(): void
    {
        $this->pendingAttendees = [];
    }

    public function setProcessingSplitAdd(bool $flag): void
    {
        $this->processingSplitAdd = $flag;
    }

    public function isProcessingSplitAdd(): bool
    {
        return $this->processingSplitAdd;
    }

    public function setRedirectNextAdd(bool $flag): void
    {
        $this->redirectNextAdd = $flag;
    }

    public function consumeRedirectFlag(): bool
    {
        $shouldRedirect = $this->redirectNextAdd;
        $this->redirectNextAdd = false;
        return $shouldRedirect;
    }

    public function getQueueCount(): int
    {
        return count($this->attendeeQueue);
    }

    public function getPendingCount(): int
    {
        return count($this->pendingAttendees);
    }
}
