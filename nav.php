<div id="bottom-nav">
  <a href="../beranda/index.php" class="nav-item">
    <img src="../img/beranda.svg" alt="Beranda" width="24"><br><small>Beranda</small>
  </a>
  <a href="../jadwal/index.php" class="nav-item">
    <img src="../img//jadwal.svg" alt="Jadwal" width="24"><br><small>Jadwal</small>
  </a>
  <a href="../tugas/index.php" class="nav-item">
    <img src="../img/tugas.svg" alt="Tugas" width="24"><br><small>Tugas</small>
  </a>
  <a href="../kelas/index.php" class="nav-item">
    <img src="../img/pengingat.svg" alt="Pengingat" width="24"><br><small>Pengingat</small>
  </a>
  <a href="../pengaturan/index.php" class="nav-item">
    <img src="../img/pengaturan.svg" alt="Pengaturan" width="24"><br><small>Pengaturan</small>
  </a>
</div>

<style>
            article.prose {
                line-height: 1.75;
                display: contents
            }

            article.prose a {
                font-weight: 500;
                text-decoration: underline
            }

            article.prose h1 {
                margin-block:0 1.23em;font-size: 2.25em;
                line-height: 1.11
            }

            article.prose h2 {
                margin-block:2em 1em;font-size: 1.5em;
                line-height: 1.34
            }

            article.prose h3 {
                margin-block:1.6em .6em;font-size: 1.25em;
                line-height: 1.6
            }

            article.prose h4 {
                margin-top: 1.5em;
                margin-bottom: .5em;
                line-height: 1.5
            }

            article.prose img {
                width: 100%;
                margin-block:2em}

            article.prose blockquote {
                margin-block:1.6em;padding: .75em 1.25em
            }

            article.prose ul,article.prose ol {
                padding-inline-start:1.5em}

            article.prose table {
                table-layout: auto;
                width: 100%;
                margin: 1.5em 0
            }

            article.prose thead th {
                vertical-align: bottom;
                font-weight: 600
            }

            article.prose tbody td {
                vertical-align: baseline
            }

            article.prose tfoot td {
                vertical-align: top
            }

            article.prose th,article.prose td {
                text-align: start;
                padding: .75em
            }

            article.prose thead th:first-child,article.prose tbody td:first-child,article.prose tfoot td:first-child {
                padding-inline-start:0}

            article.prose thead th:last-child,article.prose tbody td:last-child,article.prose tfoot td:last-child {
                padding-inline-end:0}

            article.prose thead {
                box-shadow: 0 1px #ffffffbf,0 1px
            }

            article.prose tbody tr {
                box-shadow: 0 1px #ffffffd9,0 1px
            }

            article.prose hr {
                opacity: .3;
                margin: 3em 0
            }

            #bottom-nav {
  position: fixed;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 430px;
  height: 70px;
  background-color: #121212;
  border-radius: 12px 12px 0 0;
  box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.3);
  display: flex;
  justify-content: space-around;
  align-items: center;
  z-index: 9999;
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: #b7b7b7;
  font-family: Poppins, sans-serif;
  font-size: 14px;
  text-decoration: none;
  flex: 1;
}

.nav-item.active {
  color: #ffffff;
}

.nav-item img {
  display: block;
  margin-top: 4px;
  margin-bottom: 1px;
  width: 24px;
  height: 24px;
  object-fit: contain;
}

.nav-item small {
  font-size: 10px;
}


        </style>
