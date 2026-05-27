module.exports = function(grunt) {
    'use strict';

    const pkg = grunt.file.readJSON('package.json');

    grunt.initConfig({
        pkg: pkg,

        // Clean dist folder
        clean: {
            dist: ['dist/'],
            zip: ['*.zip']
        },

        // Copy files to dist
        copy: {
            dist: {
                files: [{
                    expand: true,
                    src: [
                        '**',
                        '!node_modules/**',
                        // vendor/ IS shipped — shell:composer_no_dev runs first, so vendor
                        // holds only the runtime closure (autoload + action-scheduler + edd-sl-sdk).
                        // Excluding it ships a zip that fatals on activation (missing autoload.php).
                        '!vendor/bin/**',
                        '!src/**',
                        '!plan/**',
                        '!dist/**',
                        '!.git/**',
                        '!.github/**',
                        '!.gitignore',
                        '!**/.gitkeep',
                        '!**/.DS_Store',
                        '!.editorconfig',
                        '!.eslintrc*',
                        '!.prettierrc*',
                        '!phpcs.xml',
                        '!phpcs.xml.dist',
                        '!phpunit.xml*',
                        '!phpstan.neon*',
                        '!phpstan-baseline.neon',
                        '!phpstan-bootstrap.php',
                        '!webpack.config.js',
                        '!Gruntfile.js',
                        '!package.json',
                        '!package-lock.json',
                        '!composer.json',
                        '!composer.lock',
                        '!*.zip',
                        '!**/*.map',
                        '!tests/**',
                        '!bin/**',
                        '!**/*.sh',
                        '!**/*.md',
                        '!docs/**',
                        // Recursive variants so vendor-nested dev files (e.g.
                        // vendor/easy-digital-downloads/edd-sl-sdk/composer.lock)
                        // don't ship in the dist zip. The non-prefixed variants
                        // above only match repo-root files.
                        '!**/.editorconfig',
                        '!**/.eslintrc*',
                        '!**/.prettierrc*',
                        '!**/.gitignore',
                        '!**/.gitattributes',
                        '!**/phpunit.xml*',
                        '!**/phpcs.xml*',
                        '!**/phpstan-baseline.neon',
                        '!**/webpack.config.js',
                        '!**/package.json',
                        '!**/package-lock.json',
                        '!**/composer.lock',
                        // EDD SL SDK source assets (.scss / ES-module .js) —
                        // dead weight in the zip since the SDK's compiled
                        // build/ isn't shipped (Pro shims it via
                        // assets/edd-sl-sdk-shim/).
                        '!vendor/**/assets/src/**'
                    ],
                    dest: 'dist/mediashield/'
                }]
            }
        },

        // Create zip
        compress: {
            dist: {
                options: {
                    archive: 'mediashield-<%= pkg.version %>.zip',
                    mode: 'zip'
                },
                files: [{
                    expand: true,
                    cwd: 'dist/',
                    src: ['mediashield/**'],
                    dest: '/'
                }]
            }
        },

        // Replace version strings
        replace: {
            version: {
                src: [
                    'mediashield.php',
                    'readme.txt',
                    'includes/Core/Plugin.php'
                ],
                overwrite: true,
                replacements: [{
                    from: /Version:\s*[\d.]+/,
                    to: 'Version: <%= pkg.version %>'
                }]
            }
        },

        // Watch for changes during development
        watch: {
            scripts: {
                files: ['src/**/*.js', 'src/**/*.css'],
                tasks: ['shell:build'],
                options: { spawn: false }
            }
        },

        // Shell commands
        shell: {
            build: {
                command: 'npx wp-scripts build'
            },
            pot: {
                command: 'wp i18n make-pot . languages/mediashield.pot --domain=mediashield --exclude=node_modules,vendor,build,plan,dist,tests'
            },
            json: {
                command: 'wp i18n make-json languages/ --no-purge'
            },
            composer_no_dev: {
                command: 'composer install --no-dev --optimize-autoloader 2>/dev/null; true'
            }
        }
    });

    // Load plugins
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-text-replace');
    grunt.loadNpmTasks('grunt-shell');

    // Register tasks
    grunt.registerTask('i18n', ['shell:pot', 'shell:json']);
    grunt.registerTask('build', ['shell:build']);
    grunt.registerTask('dist', [
        'clean:dist',
        'clean:zip',
        'shell:build',
        'i18n',
        'shell:composer_no_dev',
        'copy:dist',
        'compress:dist',
        'clean:dist'
    ]);
    grunt.registerTask('default', ['build']);
};
