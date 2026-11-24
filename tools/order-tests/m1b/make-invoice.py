# tools/order-tests/m1b/make-invoice.py <out.pdf> [variant]: a made-up vendor invoice as a text PDF (never a real
# vendor's file), for M1b's drop-and-read tests. variant: match (PO = our work order, $256.83, no tax or shipping,
# shipped by USPS Priority Mail: one question, the ship via) | amount ($270.00 billed) | nopo (no PO; amount matches)
import sys
from reportlab.lib.pagesizes import letter
from reportlab.pdfgen import canvas
out, v = sys.argv[1], (sys.argv[2] if len(sys.argv) > 2 else 'match')
amt = '270.00' if v == 'amount' else '256.83'
c = canvas.Canvas(out, pagesize=letter); c.setFont('Helvetica', 11); y = 740
def line(t, x=60):
    global y; c.drawString(x, y, t); y -= 18
c.setFont('Helvetica-Bold', 16); line('Sample Print Works (made up for tests)'); c.setFont('Helvetica', 11)
line('100 Example Way, Testville AZ 85000'); y -= 10
line('Invoice #: 130929'); line('Invoice Date: 11/17/2026'); line('Due Date: 12/17/2026')
if v != 'nopo': line('Customer PO: 20261027-WO0001')
line('Ship Via: USPS Priority Mail'); y -= 10
line('Description                                   Qty        Amount')
line('Gift certificates, 130# C2S                   3000       $' + amt)
y -= 10
line('Subtotal: $' + amt); line('Sales Tax: $0.00'); line('Shipping: $0.00'); line('Total Due: $' + amt)
c.save()
