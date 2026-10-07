<?xml version="1.0" encoding="UTF-8"?>
<!--
  A sitemap is XML for crawlers, but a human opening it deserves better than
  their browser's default rendering (Safari strips the tags; Firefox prints a
  "no style information" banner). This is presentation only — crawlers ignore
  the xml-stylesheet instruction entirely, and browsers that have dropped XSLT
  simply fall back to the raw XML.
-->
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:sm="http://www.sitemaps.org/schemas/sitemap/0.9"
  xmlns:xhtml="http://www.w3.org/1999/xhtml">

  <xsl:output method="html" encoding="UTF-8" indent="yes"/>

  <xsl:template match="/">
    <html lang="en">
      <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <title>Sitemap — Contamos</title>
        <style>
          :root {
            color-scheme: light dark;
            --bg: oklch(0.993 0.003 85);
            --fg: oklch(0.2 0.012 60);
            --muted: oklch(0.505 0.018 70);
            --line: oklch(0.905 0.01 80);
            --accent: oklch(0.24 0.012 60);
          }
          @media (prefers-color-scheme: dark) {
            :root {
              --bg: oklch(0.17 0.008 60);
              --fg: oklch(0.975 0.003 85);
              --muted: oklch(0.715 0.016 75);
              --line: oklch(1 0 0 / 9%);
              --accent: oklch(0.92 0.006 85);
            }
          }
          * { box-sizing: border-box; }
          body {
            margin: 0;
            padding: clamp(2rem, 6vw, 4rem) clamp(1.25rem, 5vw, 3rem);
            background: var(--bg);
            color: var(--fg);
            font: 15px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
          }
          main { max-width: 68rem; margin: 0 auto; }
          .eyebrow {
            margin: 0;
            font: 500 0.75rem/1 ui-monospace, SFMono-Regular, Menlo, monospace;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
          }
          h1 { margin: 0.75rem 0 0; font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 600; letter-spacing: -0.02em; }
          .lede { margin: 0.75rem 0 0; max-width: 60ch; color: var(--muted); }
          .scroller { overflow-x: auto; margin-top: 2.5rem; }
          table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
          th {
            text-align: left;
            padding: 0 1rem 0.625rem 0;
            border-bottom: 1px solid var(--line);
            font: 500 0.6875rem/1 ui-monospace, SFMono-Regular, Menlo, monospace;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            white-space: nowrap;
          }
          td { padding: 0.75rem 1rem 0.75rem 0; border-bottom: 1px solid var(--line); vertical-align: top; }
          td:last-child, th:last-child { padding-right: 0; }
          a { color: var(--accent); text-decoration-thickness: 1px; text-underline-offset: 2px; }
          .num, .alt { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--muted); white-space: nowrap; }
          .alt { font-size: 0.8125rem; }
          footer { margin-top: 2.5rem; font-size: 0.8125rem; color: var(--muted); }
        </style>
      </head>
      <body>
        <main>
          <p class="eyebrow">XML Sitemap</p>
          <h1>Contamos</h1>
          <p class="lede">
            <xsl:value-of select="count(sm:urlset/sm:url)"/>
            <xsl:text> URLs. This page is a stylesheet over the raw sitemap — </xsl:text>
            <xsl:text>crawlers read the XML underneath it.</xsl:text>
          </p>

          <div class="scroller">
            <table>
              <thead>
                <tr>
                  <th>URL</th>
                  <th>Languages</th>
                  <th>Last modified</th>
                  <th>Change freq.</th>
                  <th>Priority</th>
                </tr>
              </thead>
              <tbody>
                <xsl:for-each select="sm:urlset/sm:url">
                  <tr>
                    <td>
                      <a href="{sm:loc}"><xsl:value-of select="sm:loc"/></a>
                    </td>
                    <td class="alt">
                      <xsl:for-each select="xhtml:link[@rel='alternate']">
                        <xsl:if test="position() &gt; 1"><xsl:text>, </xsl:text></xsl:if>
                        <a href="{@href}"><xsl:value-of select="@hreflang"/></a>
                      </xsl:for-each>
                    </td>
                    <td class="num">
                      <xsl:choose>
                        <xsl:when test="sm:lastmod">
                          <xsl:value-of select="substring(sm:lastmod, 1, 10)"/>
                        </xsl:when>
                        <xsl:otherwise>—</xsl:otherwise>
                      </xsl:choose>
                    </td>
                    <td class="num"><xsl:value-of select="sm:changefreq"/></td>
                    <td class="num"><xsl:value-of select="sm:priority"/></td>
                  </tr>
                </xsl:for-each>
              </tbody>
            </table>
          </div>

          <footer>
            <a href="/">contamos.xyz</a>
            <xsl:text> · </xsl:text>
            <a href="/robots.txt">robots.txt</a>
            <xsl:text> · </xsl:text>
            <a href="/llms-full.txt">llms-full.txt</a>
          </footer>
        </main>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
