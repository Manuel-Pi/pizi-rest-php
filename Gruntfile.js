module.exports = function(grunt) {
	grunt.initConfig({
		srcFile: 'src/',
		build: 'build/',
		libs: 'vendor/',
		testFile: 'tests/',
		//serverFolder: 'C:/wamp/www/pizi-rest',
		serverFolder: '../../Servers/ApacheServer/pizi-rest',
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
						dest: '<%= serverFolder %>'
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