import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import * as tar from 'tar'

const __dirname = path.join(path.dirname(fileURLToPath(import.meta.url)), '..')
const appName = 'sticky_crm'
const buildDir = path.join(__dirname, 'build')
const artifactDir = path.join(buildDir, 'artifacts', appName)

async function createRelease() {
    console.log(`🚀 Starte sauberen Build für ${appName}...`)

    // 1. Alten Build-Ordner aufräumen
    if (fs.existsSync(buildDir)) fs.rmSync(buildDir, { recursive: true, force: true })
    fs.mkdirSync(artifactDir, { recursive: true })

    // 2. Nur produktive Dateien in den Artefakt-Ordner kopieren (OHNE node_modules/.nextcloud)
    const foldersToCopy = ['appinfo', 'lib', 'l10n', 'js', 'css', 'templates', 'img', 'vendor']
    const filesToCopy = ['CHANGELOG.md', 'LICENSE.md', 'README.md']

    foldersToCopy.forEach((folder) => {
        const src = path.join(__dirname, folder)
        if (fs.existsSync(src)) fs.cpSync(src, path.join(artifactDir, folder), { recursive: true })
    })
    filesToCopy.forEach((file) => {
        const src = path.join(__dirname, file)
        if (fs.existsSync(src)) fs.cpSync(src, path.join(artifactDir, file))
    })

    console.log('📂 Archiv wird gepackt...')

    // 3. Als .tar.gz archivieren
    try {
        await tar.c(
            {
                gzip: true,
                file: path.join(buildDir, `${appName}.tar.gz`),
                cwd: path.join(buildDir, 'artifacts'),
            },
            [appName],
        )
        console.log(`✨ Fertig! Dein Release liegt unter: build/${appName}.tar.gz`)
    } catch (error) {
        console.error('❌ Fehler beim Packen des Archivs:', error)
        process.exitCode = 1
    }
}

createRelease()