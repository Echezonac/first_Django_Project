#tag Class
Protected Class App
Inherits ConsoleApplication
	#tag Event
		Function Run(args() as String) As Integer
		  // Simply shell the command line under our 64-bit parent process
		  Dim sCmd As String
		  For n As Integer = 1 to UBound(args)
		    sCmd = sCmd + args(n) + " "
		  Next n
		  Dim sh As New Shell
		  sh.Mode = 0
		  sh.TimeOut = 30000
		  sh.Execute(sCmd)
		  Print sh.Result
		  sh.Close
		  
		End Function
	#tag EndEvent


	#tag ViewBehavior
	#tag EndViewBehavior
End Class
#tag EndClass
