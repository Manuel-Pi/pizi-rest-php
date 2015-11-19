module.exports = function(grunt) {
	grunt.initConfig({
		srcFile: 'src/',
		build: 'build/',
		libs: 'vendor/',
		testFile: 'tests/',
		serverFolder: 'C:/wamp/www/pizi-rest',
		testServerFolder: 'C:/wamp/www/pizi-rest-test',
		//serverFolder: 'C:/dev/appl/apache-2.2.22/htdocs/pizi-rest',
		copy: {
			deployDev : {
				files : [
					{
						expand: true,
						cwd: '<%= srcFile %>',
						src: [
							'**',
							'.htaccess',
							],
						dest: '<%= serverFolder %>'
					},
					{
						expand: true,
						cwd: '<%= libs %>',
						src: ['**/*.php'],
						dest: '<%= serverFolder %>/lib'
					},
					{
						expand: true,
						cwd: '<%= testFile %>',
						src: ['**'],
						dest: '<%= testServerFolder %>'
					},
					{
						expand: true,
						cwd: 'node_modules/',
						src: [
							'jquery/dist/jquery.min.js',
							'qunitjs/qunit/**'
							],
						dest: '<%= testServerFolder %>',
						flatten: true
					}
				]
			}
		},
		clean: {
			options: {
				force: true
			},
			deployDev: '<%= serverFolder %>'
		}
	});
	grunt.loadNpmTasks('grunt-contrib-copy');
	grunt.loadNpmTasks('grunt-contrib-clean');
	grunt.registerTask('deployDev', ['clean:deployDev', 'copy:deployDev']);
};