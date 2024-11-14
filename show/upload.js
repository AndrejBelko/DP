const { exec } = require('child_process');
const path = require('path');
const fs = require('fs');

// Get the file path from command line arguments
const uploadedFilePath = process.argv[2];
const logFilePath = 'script.log';

// Construct the command to run the Python script
const pythonScript = `python3 mapmatch.py '${uploadedFilePath}'`; // Adjust as necessary

exec(pythonScript, (error, stdout, stderr) => {
    const logOutput = `STDOUT: ${stdout}\nSTDERR: ${stderr}\nERROR: ${error ? error.message : 'None'}\n\n`;

    // Write to log file
    fs.appendFileSync(logFilePath, logOutput, 'utf8');
});