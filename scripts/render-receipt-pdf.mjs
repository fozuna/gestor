import fs from 'node:fs/promises'
import puppeteer from 'puppeteer-core'

const [, , htmlPath, outputPath, browserPath] = process.argv

if (!htmlPath || !outputPath || !browserPath) {
  console.error('Uso: node render-receipt-pdf.mjs <htmlPath> <outputPath> <browserPath>')
  process.exit(1)
}

const html = await fs.readFile(htmlPath, 'utf8')

const browser = await puppeteer.launch({
  executablePath: browserPath,
  headless: true,
  args: [
    '--disable-gpu',
    '--disable-dev-shm-usage',
    '--disable-setuid-sandbox',
    '--no-sandbox',
  ],
})

try {
  const page = await browser.newPage()
  await page.setViewport({ width: 320, height: 1200, deviceScaleFactor: 1 })
  await page.setContent(html, { waitUntil: 'networkidle0' })
  await page.emulateMediaType('screen')

  const heightPx = await page.evaluate(() => {
    const doc = document.documentElement
    const body = document.body
    return Math.max(
      doc.scrollHeight,
      body.scrollHeight,
      doc.offsetHeight,
      body.offsetHeight,
      doc.clientHeight,
      body.clientHeight,
    )
  })

  const heightMm = Math.max(90, Math.min(600, ((heightPx + 12) * 25.4) / 96))

  await page.pdf({
    path: outputPath,
    width: '80mm',
    height: `${heightMm}mm`,
    printBackground: true,
    preferCSSPageSize: false,
    margin: {
      top: '0mm',
      right: '0mm',
      bottom: '0mm',
      left: '0mm',
    },
  })

  console.log(JSON.stringify({ outputPath, heightPx, heightMm }))
} finally {
  await browser.close()
}

