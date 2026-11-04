' ============================================================================
'  Puente de GPU, sin ventana
' ============================================================================
'
'  Este archivo arranca tools\gpu-bridge.ps1 con la ventana oculta, para dejarlo
'  corriendo en segundo plano al iniciar el proyecto o al iniciar sesion.
'
'  Se usa un .vbs porque es la unica forma limpia en Windows de lanzar algo sin
'  que parpadee una consola. Un .cmd siempre muestra la ventana un instante.
'
'  Doble clic aqui no hace nada visible: eso es lo esperado. Para comprobar que
'  esta corriendo, mira storage\app\gpu\gpu.json (se actualiza cada dos segundos)
'  o el indicador de GPU en el panel de IA.
' ============================================================================

Option Explicit

Dim shell, carpeta, script, comando

Set shell = CreateObject("WScript.Shell")

' Carpeta de este archivo, un nivel arriba (la raiz del proyecto)
carpeta = Left(WScript.ScriptFullName, InStrRev(WScript.ScriptFullName, "\") - 1) & "\.."

script = shell.ExpandEnvironmentStrings(carpeta) & "\tools\gpu-bridge.ps1"

If Not CreateObject("Scripting.FileSystemObject").FileExists(script) Then
    ' Nada de mensajes en pantalla: solo se deja constancia en un archivo.
    Dim log
    Set log = CreateObject("Scripting.FileSystemObject").OpenTextFile( _
        shell.ExpandEnvironmentStrings(carpeta) & "\storage\app\gpu\error.txt", 8, True)
    log.WriteLine Now & "  No se encontro " & script
    log.Close
    WScript.Quit 1
End If

' 0 = ventana oculta, False = no esperar a que termine
comando = "powershell.exe -NoProfile -ExecutionPolicy Bypass -File """ & script & """"

shell.Run comando, 0, False
