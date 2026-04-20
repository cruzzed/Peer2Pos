# Peer2Pos — Receipt Printer & Cash Drawer Integration

> Research document. Last updated: 2026-04-19.
> Stack: Laravel 13 / Livewire 4 / Alpine.js, native Windows TALL stack.
> Chosen approach: all printing handled server-side by PHP — no browser APIs involved.

---

## Table of Contents

1. [ESC/POS Protocol](#1-escpos-protocol)
2. [Cash Drawer Integration](#2-cash-drawer-integration)
3. [Browser-Side Approaches (reference only)](#3-browser-side-approaches-reference-only)
4. [Server-Side PHP — mike42/escpos-php](#4-server-side-php--mike42escpos-php)
5. [Architecture Options Comparison](#5-architecture-options-comparison)
6. [ESC/POS Receipt Formatting Reference](#6-escpos-receipt-formatting-reference)
7. [Recommendations for Peer2Pos](#7-recommendations-for-peer2pos)

---

## 1. ESC/POS Protocol

### What It Is

ESC/POS (Escape/Point-of-Sale) is a command language developed by Epson in the 1980s. Every thermal receipt printer encountered in a retail POS context speaks some dialect of it. Commands are raw byte sequences sent over the transport layer the printer exposes (USB, RS-232 serial, TCP socket). The printer is a **streaming device** — it has no page concept. Bytes flow in, paper flows out.

The protocol has no ACK/NACK handshake in its baseline form. There is no PDF, PostScript, or rasterization pipeline.

### Command Structure

Every command begins with an ASCII control character — almost always `ESC` (`0x1B`) or `GS` (`0x1D`). The escape byte is followed by one or more parameter bytes. Text that is not part of a command is printed literally.

```
[ESC/GS byte] [command byte] [parameter bytes...] [data bytes if applicable]
```

### Common ESC/POS Commands Reference

| Function | Hex bytes | Notes |
|---|---|---|
| Initialize printer | `1B 40` | ESC @ — always send first |
| Bold on | `1B 45 01` | ESC E 1 |
| Bold off | `1B 45 00` | ESC E 0 |
| Align left | `1B 61 00` | ESC a 0 |
| Align center | `1B 61 01` | ESC a 1 |
| Align right | `1B 61 02` | ESC a 2 |
| Double-width | `1B 21 20` | ESC ! 0x20 |
| Double-height | `1B 21 10` | ESC ! 0x10 |
| Double both | `1B 21 30` | ESC ! 0x30 |
| Normal size | `1B 21 00` | ESC ! 0 |
| Line feed | `0A` | LF |
| Feed N lines | `1B 64 NN` | ESC d N |
| Full cut | `1D 56 00` | GS V 0 |
| Partial cut | `1D 56 01` | GS V 1 |
| Cut + feed N dots | `1D 56 42 NN` | GS V B N |
| Open drawer pin 2 | `1B 70 00 19 FA` | ESC p — see §2 |
| Open drawer pin 5 | `1B 70 01 19 FA` | ESC p — see §2 |

### `ESC !` Mode Byte Bitmask

`ESC !` (`1B 21`) takes a single byte bitmask:

| Bit | Hex | Effect |
|---|---|---|
| 0 | `0x01` | Font B (smaller) |
| 3 | `0x08` | Bold |
| 4 | `0x10` | Double-height |
| 5 | `0x20` | Double-width |
| 7 | `0x80` | Underline |

### Paper Column Width

- **80mm paper:** 42–48 characters per line at normal font size (varies by DPI/font).
- **58mm paper:** ~32 characters per line.

Always verify on the specific printer via a self-test printout (hold Feed at power-on).

---

## 2. Cash Drawer Integration

### Physical Connection

Cash drawers do **not** connect directly to the computer. They have a 6-pin RJ-11/RJ-12 port labeled "DK" (Drawer Kick) or "CD" (Cash Drawer) that plugs into the matching port on the receipt printer. The printer supplies 24V DC to the solenoid inside the drawer when the ESC/POS kick command is received.

**Typical Epson RJ-11 pinout:**

| Pin | Function |
|---|---|
| 1 | Frame ground |
| 2 | Drawer kick signal pin 2 |
| 3 | +24V |
| 4 | GND |
| 5 | Drawer kick signal pin 5 |
| 6 | Drawer open/closed status sense |

### ESC/POS Kick Command

```
ESC p m t1 t2
1B  70 m  t1 t2
```

- `m` = pin selector: `0x00` = pin 2, `0x01` = pin 5
- `t1` = pulse ON time in units of 2ms (`0x19` = 25 × 2ms = 50ms)
- `t2` = pulse OFF time in units of 2ms (`0xFA` = 250 × 2ms = 500ms)

**Bytes for pin 2, 50ms pulse (most common):**
```
1B 70 00 19 FA
```

The kick command is sent to the **printer**, not to the drawer directly. The drawer has no independent serial/USB interface when using the DK port.

### Drawer Status Sense

Some printers return a status byte when you send `DLE EOT 1` (`10 04 01`). Bit 2 of the returned byte indicates whether the drawer is open or closed. Useful for detecting a cashier who left the drawer open.

### Direct-Connect USB Cash Drawers

A small number of cash drawers (APG series, some Metapace models) have a native USB or RS-232 port independently. These are uncommon — if a drawer only has a DK port, it must go through the printer.

---

## 3. Browser-Side Approaches (reference only)

> **Not used in Peer2Pos.** Printing is handled entirely server-side. This section is kept as reference for why browser approaches were not chosen.

### Why `window.print()` Does Not Work

`window.print()` sends the page through the OS print dialog as a GDI/PostScript/PDF job. The output goes through the Windows print spooler, is rendered as a full-page raster image, and formatted as an A4/Letter document. The thermal printer will either print a giant bitmap, print garbage, or reject the job. CSS `@media print` does not fix this — the spooler applies its own margins and page model.

### WebUSB API

Chrome/Edge only. Requires replacing the Windows `usbprint.sys` driver with WinUSB (via Zadig), permanently removing the device from the Windows print subsystem. Not worth the tradeoff.

### Web Serial API

Chrome/Edge only. Works via USB virtual COM port. Viable if a browser approach were ever needed, but backend printing is simpler and has no browser restrictions.

### QZ Tray

Java-based WebSocket bridge. Works in any browser. Requires Java + QZ Tray running as a Windows startup process. ~$99/yr commercial license for a no-warning browser dialog. Unnecessary given backend printing is available.

---

## 4. Server-Side PHP — mike42/escpos-php

The canonical PHP library for ESC/POS. Install via Composer:

```bash
composer require mike42/escpos-php
```

### Connector Types

| Connector class | Use case |
|---|---|
| `NetworkPrintConnector` | TCP/IP printer on LAN (IP:9100) |
| `FilePrintConnector` | `COM3` (Windows native USB/serial) |
| `DummyPrintConnector` | Testing — captures bytes to a string |

### USB Printer via FilePrintConnector (Windows native)

PHP running natively on Windows can write directly to the printer's COM port. The COM port number is found in Windows Device Manager under "Ports (COM & LPT)".

```php
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

$connector = new FilePrintConnector('COM3');
$printer   = new Printer($connector);

$printer->initialize();
$printer->text("RECEIPT\n");
$printer->cut();
$printer->pulse(0, 50, 500);
$printer->close();
```

### Network Printer via NetworkPrintConnector

Ethernet printers (or USB printers on a print server) listen on port 9100 by default (JetDirect raw socket protocol).

```php
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

$connector = new NetworkPrintConnector('192.168.1.50', 9100);
$printer   = new Printer($connector);

$printer->initialize();
$printer->text("RECEIPT\n");
$printer->cut();
$printer->pulse(0, 50, 500);
$printer->close();
```

### DummyPrintConnector — Tests Only

```php
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;

$connector = new DummyPrintConnector();
$printer   = new Printer($connector);
$printer->initialize();
$printer->cut();
$printer->pulse(0, 50, 500);
$printer->close();

$rawBytes = $connector->getData();
// e.g. assert str_contains($rawBytes, "\x1D\x56\x00") — full cut byte present
```

---

## 5. Architecture Options Comparison

> **Chosen for Peer2Pos:** Backend PHP (Option B/C). PHP runs natively on Windows alongside the printer — no browser involvement.

| | QZ Tray | Web Serial | **Backend PHP** ✓ | CSS Print |
|---|---|---|---|---|
| Browser involvement | JS + WebSocket | JS + serial API | **None** | JS + dialog |
| USB printer (Windows native) | Yes | Yes (virtual COM) | **Yes (COM port)** | No |
| Network printer | Yes | No | **Yes** | No |
| Cash drawer | Yes | Yes | **Yes** | No |
| External dependency | Java + QZ Tray | None | **None** | None |
| Commercial cost | ~$99/yr | Free | **Free** | Free |
| Setup complexity | Medium | Low | **Low** | — |

---

## 6. ESC/POS Receipt Formatting Reference

### Full Receipt Example

```php
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

$connector = new NetworkPrintConnector('192.168.1.50', 9100);
$printer   = new Printer($connector);

// HEADER
$printer->initialize();
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT);
$printer->text("PEER2POS\n");
$printer->selectPrintMode();  // reset
$printer->text("123 Main Street\n");
$printer->text(str_repeat('=', 32) . "\n");

// META
$printer->setJustification(Printer::JUSTIFY_LEFT);
$printer->text('Date:    ' . now()->format('Y-m-d H:i:s') . "\n");
$printer->text('Cashier: ' . $transaction->cashier_name . "\n");
$printer->text('Ref:     ' . substr($transaction->id, 0, 8) . "\n");
$printer->text(str_repeat('-', 32) . "\n");

// ITEMS
foreach ($transaction->items as $item) {
    $name  = substr($item->product_name . ' / ' . $item->qty_type_name, 0, 20);
    $right = 'x' . $item->qty . ' ' . number_format($item->subtotal, 2);
    $line  = str_pad($name, 20) . str_pad($right, 12, ' ', STR_PAD_LEFT);
    $printer->text($line . "\n");
}

$printer->text(str_repeat('-', 32) . "\n");

// TOTAL
$printer->setJustification(Printer::JUSTIFY_RIGHT);
$printer->setEmphasis(true);
$printer->setTextSize(2, 2);
$printer->text('TOTAL: ' . number_format($transaction->total_amount, 2) . "\n");
$printer->setTextSize(1, 1);
$printer->setEmphasis(false);
$printer->setJustification(Printer::JUSTIFY_LEFT);
$printer->text('Payment: ' . strtoupper($transaction->payment_method) . "\n");

// BARCODE (transaction short ref)
$printer->text("\n");
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->setBarcodeHeight(60);
$printer->setBarcodeTextPosition(Printer::BARCODE_TEXT_BELOW);
$printer->barcode(substr($transaction->id, 0, 8), Printer::BARCODE_CODE39);

// QR CODE
$printer->qrCode($transaction->id, Printer::QR_ECLEVEL_L, 5);

// FOOTER + CUT + DRAWER
$printer->text("\nThank you!\n");
$printer->feed(4);
$printer->cut();
$printer->pulse(0, 50, 500);  // kick cash drawer

$printer->close();
```

### Barcode Types

| Constant | Type | Max chars |
|---|---|---|
| `BARCODE_UPCA` | UPC-A | 12 |
| `BARCODE_UPCE` | UPC-E | 8 |
| `BARCODE_JAN13` | EAN-13 | 13 |
| `BARCODE_JAN8` | EAN-8 | 8 |
| `BARCODE_CODE39` | Code 39 | 20 |
| `BARCODE_CODE128` | Code 128 | 80 |

### Manufacturer-Specific Notes

| Brand | Notes |
|---|---|
| **Epson TM-series** | Reference implementation of ESC/POS. All commands above apply directly. |
| **Star Micronics** | Newer models use STAR-PRN but most also support ESC/POS mode (check DIP switches). Star partial cut: `1B 64 30` instead of `1D 56 01`. |
| **Bixolon** | ESC/POS compatible, standard cut and kick commands. |
| **Citizen** | ESC/POS compatible. May require extra feed lines before cut to avoid clipping. |
| **Generic / HOIN / Rongta** | Usually ESC/POS compatible but may not support QR codes or barcodes. Test via self-test print. |

---

## 7. Recommendations for Peer2Pos

**All printing is handled entirely by PHP on the backend.** PHP runs natively on Windows alongside the printer. The Livewire `checkout()` method prints directly — the browser is uninvolved.

### USB Printer (Windows native)

**Connector:** `FilePrintConnector('COM3')` — COM port from Windows Device Manager.

### Network Printer on LAN

**Connector:** `NetworkPrintConnector('192.168.x.x', 9100)` — no OS setup needed.

### Livewire Integration

Print directly inside `checkout()` after the transaction is committed:

```php
// In PosTerminal.php
public function checkout(): void
{
    // ... existing transaction creation ...

    try {
        $this->printReceipt($transaction);
    } catch (\Throwable $e) {
        logger()->error('Receipt print failed', [
            'transaction_id' => $transaction->id,
            'error'          => $e->getMessage(),
        ]);
        $this->addError('printer', 'Receipt could not be printed. Transaction was saved.');
    }

    // ... notifications / cart reset ...
}

private function printReceipt(Transaction $transaction): void
{
    $type = config('pos.printer_type');

    if ($type === 'none') {
        return;
    }

    $connector = match ($type) {
        'network' => new NetworkPrintConnector(
                         config('pos.printer_ip'),
                         config('pos.printer_port')
                     ),
        'file'    => new FilePrintConnector(config('pos.printer_path')),
        default   => throw new \InvalidArgumentException("Unknown printer type: {$type}"),
    };

    $printer = new Printer($connector);

    $printer->initialize();
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->setEmphasis(true);
    $printer->text(config('app.name') . "\n");
    $printer->setEmphasis(false);
    $printer->text($transaction->created_at->format('Y-m-d H:i:s') . "\n");
    $printer->text('Cashier: ' . $transaction->cashier_name . "\n");
    $printer->setJustification(Printer::JUSTIFY_LEFT);
    $printer->text(str_repeat('-', 32) . "\n");

    foreach ($transaction->items as $item) {
        $name  = substr($item->product_name . ' / ' . $item->qty_type_name, 0, 20);
        $right = 'x' . $item->qty . ' ' . number_format($item->subtotal, 2);
        $printer->text(str_pad($name, 20) . str_pad($right, 12, ' ', STR_PAD_LEFT) . "\n");
    }

    $printer->text(str_repeat('-', 32) . "\n");
    $printer->setJustification(Printer::JUSTIFY_RIGHT);
    $printer->setEmphasis(true);
    $printer->text('TOTAL: ' . number_format($transaction->total_amount, 2) . "\n");
    $printer->setEmphasis(false);
    $printer->setJustification(Printer::JUSTIFY_LEFT);
    $printer->text('Payment: ' . strtoupper($transaction->payment_method) . "\n");
    $printer->feed(3);
    $printer->cut();
    $printer->pulse(0, 50, 500);  // open cash drawer

    $printer->close();
}
```

`$transaction->items` must be loaded before this call — either eager-load on creation or call `$transaction->load('items')` beforehand.

The try/catch ensures a printer failure never rolls back or blocks a completed transaction.

### `.env` / Config Keys

```dotenv
POS_PRINTER_TYPE=file      # file | network | none
POS_PRINTER_PATH=COM3      # for type=file — COM port from Device Manager
POS_PRINTER_IP=            # for type=network
POS_PRINTER_PORT=9100      # for type=network
```

```php
// config/pos.php
return [
    'printer_type' => env('POS_PRINTER_TYPE', 'none'),
    'printer_path' => env('POS_PRINTER_PATH', 'COM3'),
    'printer_ip'   => env('POS_PRINTER_IP'),
    'printer_port' => (int) env('POS_PRINTER_PORT', 9100),
];
```

### Implementation Order

1. `composer require mike42/escpos-php`
2. Create `config/pos.php`.
3. Add `POS_PRINTER_TYPE=none` to `.env.example` and to each node's `.env`.
4. Add `printReceipt(Transaction $transaction): void` to `PosTerminal.php`.
5. Call it inside `checkout()` wrapped in try/catch.
6. Set the correct `POS_PRINTER_TYPE` and path/IP per terminal.
7. Test with `POS_PRINTER_TYPE=none` first, then with a real printer.
