# Script Pengujian Transaksi Midtrans QRIS
# Cara menjalankan di PowerShell: .\create_test_payment.ps1

$serverKey = "Mid-server-YOUR_SERVER_KEY_HERE:"
$base64Auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes($serverKey))

$randomId = Get-Random -Minimum 1000 -Maximum 9999
$orderId = "SMARTPLUG-$randomId"
$amount = 2000

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host " MEMBUAT TRANSAKSI UJI COBA MIDTRANS QRIS " -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Order ID : $orderId"
Write-Host "Nominal  : Rp $amount"
Write-Host "Sedang menghubungi Midtrans..."

$headers = @{
    "Accept" = "application/json"
    "Content-Type" = "application/json"
    "Authorization" = "Basic $base64Auth"
}

$body = @{
    "transaction_details" = @{
        "order_id" = $orderId
        "gross_amount" = $amount
    }
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "https://app.sandbox.midtrans.com/snap/v1/transactions" -Method Post -Headers $headers -Body $body
    Write-Host "`n[SUKSES] Transaksi berhasil dibuat!" -ForegroundColor Green
    Write-Host "Link Pembayaran QRIS Anda:" -ForegroundColor Yellow
    Write-Host $response.redirect_url -ForegroundColor White
    Write-Host "`nSilakan buka link di atas di browser untuk melakukan pembayaran / simulasi."
} catch {
    Write-Host "`n[GAGAL] Terjadi kesalahan: $_" -ForegroundColor Red
}
