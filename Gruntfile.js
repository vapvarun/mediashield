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
                        '!vendor/**',
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
                        '!docs/**'
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
